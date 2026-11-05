<?php

namespace Xelqun\Forms\Testing;

use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Response;
use Xelqun\Forms\Form;

/**
 * Drive a form's endpoints from a Laravel test the way the browser does.
 * In Pest, add uses(InteractsWithForms::class) to the test file.
 *
 * @api
 */
trait InteractsWithForms
{
    /**
     * Send a live update. $data defaults to the form's own state.
     *
     * @param  array<int, string>  $changed  The paths the user changed.
     * @param  array<string, mixed>|null  $data
     * @param  array<string, array<int, string>>  $dependencies  Node key => paths its closures read.
     * @return TestResponse<Response>
     */
    public function updateForm(Form $form, array $changed, ?array $data = null, array $dependencies = []): TestResponse
    {
        return $this->postJson(route('xelqun.forms.update'), [
            'token' => $form->makeToken(),
            'seq' => 1,
            'state' => ['data' => $data ?? $form->getData()],
            'changed' => $changed,
            'deps' => $dependencies,
        ]);
    }

    /**
     * Search a field. $data defaults to the form's own state.
     *
     * @param  array<string, mixed>|null  $data
     * @return TestResponse<Response>
     */
    public function searchForm(Form $form, string $path, string $search = '', ?array $data = null): TestResponse
    {
        return $this->postJson(route('xelqun.forms.search'), [
            'token' => $form->makeToken(),
            'path' => $path,
            'search' => $search,
            'state' => ['data' => $data ?? $form->getData()],
        ]);
    }

    /**
     * Upload a file to a field. The JSON response holds the token to submit.
     *
     * @return TestResponse<Response>
     */
    public function uploadToForm(Form $form, string $path, UploadedFile $file): TestResponse
    {
        return $this->post(route('xelqun.forms.upload'), [
            'token' => $form->makeToken(),
            'path' => $path,
            'file' => $file,
        ], ['Accept' => 'application/json']);
    }

    /**
     * The schema node of a component, found by its key, including nodes in rows.
     *
     * @return array<string, mixed>
     */
    public function formNode(Form $form, string $key): array
    {
        $node = $this->findFormNode($form->toSchema()['components'], $key);

        if ($node === null) {
            Assert::fail("The form has no node [{$key}].");
        }

        return $node;
    }

    /**
     * Plugin types need a vendor prefix: unprefixed names are for built-ins.
     */
    public function assertNamespacedField(Form $form, string $key): void
    {
        $type = $this->formNode($form, $key)['type'];

        Assert::assertMatchesRegularExpression('#^[a-z0-9-]+/[a-z0-9-]+$#', $type, "The type of [{$key}] has no vendor prefix.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>|null
     */
    private function findFormNode(array $nodes, string $key): ?array
    {
        foreach ($nodes as $node) {
            if ($node['key'] === $key) {
                return $node;
            }

            $rows = array_values((array) ($node['props']->rows ?? []));

            foreach ([$node['children'] ?? [], ...$rows] as $children) {
                $found = $this->findFormNode($children, $key);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
