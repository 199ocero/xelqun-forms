<?php

namespace Xelqun\Actions;

use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Xelqun\Forms\Components\Component;
use Xelqun\Forms\Components\Field;
use Xelqun\Forms\Contracts\NeedsServer;
use Xelqun\Forms\Support\SafeUrl;
use Xelqun\Forms\Support\StatePath;
use Xelqun\Notifications\Notification;

/**
 * A button that runs a closure on the server, optionally through a modal with
 * its own fields. Put it in Actions, a field slot or a callout footer.
 *
 * An action does nothing until authorize() allows it. Form::authorize() doesn't
 * count, because being allowed to edit a form isn't being allowed to do this.
 *
 * Every call rebuilds the form from trusted state, finds the action and checks
 * it again, so a tampered request can only reach what the user could reach.
 *
 * @api
 */
class Action extends Component implements NeedsServer
{
    protected string|Closure|null $label = null;

    protected string|Closure|null $icon = null;

    protected string|Closure|null $color = null;

    protected string|Closure|null $tooltip = null;

    protected string $style = 'button';

    protected bool|Closure $isDisabled = false;

    protected int|string|Closure|null $badge = null;

    protected string|Closure|null $badgeColor = null;

    protected bool|Closure $isOutlined = false;

    protected string|Closure|null $size = null;

    /** @var array<int, string>|Closure */
    protected array|Closure $keyBindings = [];

    protected int|Closure|null $rateLimit = null;

    protected bool|Closure $hasAuthorizationTooltip = false;

    protected bool|Closure $isSlideOver = false;

    protected bool|string|Closure|null $authorization = null;

    protected bool|Closure $requiresConfirmation = false;

    protected string|Closure|null $modalHeading = null;

    protected string|Closure|null $modalDescription = null;

    protected string|Closure|null $modalSubmitActionLabel = null;

    protected string|Closure|null $modalCancelActionLabel = null;

    protected string|Closure|null $modalWidth = null;

    protected string|Closure|null $modalIcon = null;

    protected string|Closure|null $modalIconColor = null;

    protected string|Closure|null $modalAlignment = null;

    protected string|Closure|null $modalFooterActionsAlignment = null;

    protected bool|Closure $hasModalCloseButton = true;

    protected bool|Closure $closesModalByClickingAway = true;

    /** @var array<int, Component>|Closure */
    protected array|Closure $modalComponents = [];

    /** @var array<string, mixed>|Closure|null */
    protected array|Closure|null $fillUsing = null;

    protected ?Closure $action = null;

    protected string|Closure|null $successRedirectUrl = null;

    protected string|Closure|null $successNotificationTitle = null;

    /** @var class-string<Model>|Closure|null */
    protected string|Closure|null $model = null;

    protected ?Closure $recordUsing = null;

    protected bool $sendsResult = false;

    protected string|Closure|null $url = null;

    protected bool|Closure $opensUrlInNewTab = false;

    final public function __construct(protected string $name) {}

    public static function make(string $name): static
    {
        return (new static($name))->configure();
    }

    public function getType(): string
    {
        return 'action';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function needsServer(): bool
    {
        return $this->url === null;
    }

    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * primary, danger or gray.
     */
    public function color(string|Closure|null $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function tooltip(string|Closure|null $tooltip): static
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    /**
     * A count or short word in the button's corner.
     */
    public function badge(int|string|Closure|null $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * primary, danger, info, success, warning or gray. Primary unless you choose another.
     */
    public function badgeColor(string|Closure|null $color): static
    {
        $this->badgeColor = $color;

        return $this;
    }

    /**
     * A border in the action's color instead of a filled button.
     */
    public function outlined(bool|Closure $condition = true): static
    {
        $this->isOutlined = $condition;

        return $this;
    }

    /**
     * sm, md or lg. Small unless you choose another.
     */
    public function size(string|Closure|null $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * Keys that click the button, such as ['mod+s'], where mod is Command on a
     * Mac and Ctrl elsewhere. Combine ctrl, meta, shift and alt with one key.
     *
     * @param  array<int, string>|Closure  $keys
     */
    public function keyBindings(array|Closure $keys): static
    {
        $this->keyBindings = $keys;

        return $this;
    }

    /**
     * How many times a minute one user, or one address when signed out, may run
     * this action. Past that the request is refused with a 429.
     */
    public function rateLimit(int|Closure|null $attempts): static
    {
        $this->rateLimit = $attempts;

        return $this;
    }

    /**
     * When authorize() says no, show the action disabled with the policy's
     * message as its tooltip, instead of hiding it. A no without a message
     * still hides it.
     */
    public function authorizationTooltip(bool|Closure $condition = true): static
    {
        $this->hasAuthorizationTooltip = $condition;

        return $this;
    }

    /**
     * Open the modal as a panel that slides in from the side.
     */
    public function slideOver(bool|Closure $condition = true): static
    {
        $this->isSlideOver = $condition;

        return $this;
    }

    public function button(): static
    {
        $this->style = 'button';

        return $this;
    }

    public function link(): static
    {
        $this->style = 'link';

        return $this;
    }

    /**
     * Show only the icon. The label stays for screen readers.
     */
    public function iconButton(): static
    {
        $this->style = 'icon';

        return $this;
    }

    public function disabled(bool|Closure $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

    /**
     * Who may use this action: a closure, a boolean, or a policy ability checked
     * against the action's record, or against model() when there is none. Only
     * a yes lets it run. It shows only to those it would run for, and the check
     * repeats on every call.
     */
    public function authorize(bool|string|Closure $authorization): static
    {
        $this->authorization = $authorization;

        return $this;
    }

    /**
     * Ask before running. It only helps the user: nothing stops a request that
     * skips the dialog, so authorize() is what protects anything.
     */
    public function requiresConfirmation(bool|Closure $condition = true): static
    {
        $this->requiresConfirmation = $condition;

        return $this;
    }

    public function modalHeading(string|Closure|null $heading): static
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function modalDescription(string|Closure|null $description): static
    {
        $this->modalDescription = $description;

        return $this;
    }

    public function modalSubmitActionLabel(string|Closure|null $label): static
    {
        $this->modalSubmitActionLabel = $label;

        return $this;
    }

    public function modalCancelActionLabel(string|Closure|null $label): static
    {
        $this->modalCancelActionLabel = $label;

        return $this;
    }

    /**
     * sm, md, lg, xl, 2xl, 3xl, 4xl or 5xl.
     */
    public function modalWidth(string|Closure|null $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    /**
     * An icon in a tinted circle beside the heading. A confirmation has a
     * warning triangle unless you choose another.
     */
    public function modalIcon(string|Closure|null $icon): static
    {
        $this->modalIcon = $icon;

        return $this;
    }

    /**
     * primary, danger, info, success, warning or gray. A confirmation takes the
     * action's own color unless you choose another.
     */
    public function modalIconColor(string|Closure|null $color): static
    {
        $this->modalIconColor = $color;

        return $this;
    }

    /**
     * How the heading sits: start or center. A confirmation is centered, and a
     * modal with fields starts on the left.
     */
    public function modalAlignment(string|Closure|null $alignment): static
    {
        $this->modalAlignment = $alignment;

        return $this;
    }

    /**
     * Where the buttons sit: start, center, end or full. A confirmation is full,
     * and a modal with fields ends on the right.
     */
    public function modalFooterActionsAlignment(string|Closure|null $alignment): static
    {
        $this->modalFooterActionsAlignment = $alignment;

        return $this;
    }

    /**
     * The X in the corner of a modal with fields. A confirmation has none,
     * because it wants an answer.
     */
    public function modalCloseButton(bool|Closure $condition = true): static
    {
        $this->hasModalCloseButton = $condition;

        return $this;
    }

    /**
     * Whether a click outside closes a modal with fields, losing what was typed.
     * A confirmation never closes this way.
     */
    public function closeModalByClickingAway(bool|Closure $condition = true): static
    {
        $this->closesModalByClickingAway = $condition;

        return $this;
    }

    /**
     * Fields for a modal. Their validated values reach action() as $data.
     *
     * @param  array<int, Component>|Closure  $components
     */
    public function schema(array|Closure $components): static
    {
        $this->modalComponents = $components;

        return $this;
    }

    /**
     * What the modal's fields start with. Runs on every request the modal makes,
     * so it must give the same result each time.
     *
     * @param  array<string, mixed>|Closure  $data
     */
    public function fillForm(array|Closure $data): static
    {
        $this->fillUsing = $data;

        return $this;
    }

    /**
     * The work to do, inside a transaction. It gets $data, $get, $set and
     * $record, which here is the record of the row it sits in. Throw a
     * ValidationException to stop and show errors, which rolls everything back.
     */
    public function action(?Closure $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Send what action() returns to the browser, for a renderer that asks for it.
     * It is off so that a closure can't leak a value by returning it.
     */
    public function sendsResult(bool $condition = true): static
    {
        $this->sendsResult = $condition;

        return $this;
    }

    public function hasResult(): bool
    {
        return $this->sendsResult;
    }

    /**
     * Go to a URL instead of running anything, so the button never reaches the
     * server. It still needs authorize(). The action hides itself when the URL
     * isn't http(s), mailto, tel or a path on this site, because a closure may
     * build it from a record.
     */
    public function url(string|Closure|null $url, bool|Closure $openInNewTab = false): static
    {
        $this->url = $url;
        $this->opensUrlInNewTab = $openInNewTab;

        return $this;
    }

    public function getUrl(): ?string
    {
        return SafeUrl::filter($this->evaluate($this->url));
    }

    /**
     * Where to go after the action runs, for a page that won't exist any more,
     * as after deleting its record. An unsafe URL is ignored.
     */
    public function successRedirectUrl(string|Closure|null $url): static
    {
        $this->successRedirectUrl = $url;

        return $this;
    }

    public function getSuccessRedirectUrl(): ?string
    {
        return SafeUrl::filter($this->evaluate($this->successRedirectUrl));
    }

    /**
     * Tell the user it worked, once action() has returned. For anything more,
     * send a Notification from the closure.
     */
    public function successNotificationTitle(string|Closure|null $title): static
    {
        $this->successNotificationTitle = $title;

        return $this;
    }

    /**
     * The model modal fields read relationships from, and the policy's subject
     * when there is no record.
     *
     * @param  class-string<Model>|Closure|null  $model
     */
    public function model(string|Closure|null $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        return $this->evaluate($this->model);
    }

    /**
     * The record this action works on, instead of the row's or the form's. It
     * becomes $record everywhere, and a policy ability never falls back to the
     * model, so finding nothing means no. Give it no closure that trusts posted
     * state: it has to look the record up through a query the user is limited to.
     */
    public function record(?Closure $using): static
    {
        $this->recordUsing = $using;

        return $this;
    }

    public function getRecord(): ?Model
    {
        return $this->recordUsing === null ? $this->getForm()->getRecordFor($this) : ($this->recordUsing)();
    }

    public function isAuthorized(): bool
    {
        return $this->inspectAuthorization()->allowed();
    }

    /**
     * Why authorize() said yes or no, with the policy's message when it gave one.
     */
    protected function inspectAuthorization(): Response
    {
        if (is_string($this->authorization)) {
            if ($this->recordUsing !== null) {
                $record = $this->getRecord();

                return $record === null ? Response::deny() : Gate::inspect($this->authorization, $record);
            }

            $subject = $this->getRecord() ?? $this->getModel();

            // With nothing to ask a policy about, as on a page of its own, the ability is a plain gate.
            if ($subject !== null) {
                return Gate::inspect($this->authorization, $subject);
            }

            // A gate that wants a model can't be asked without one, and that means no, not a crash.
            return rescue(fn (): Response => Gate::inspect($this->authorization), Response::deny(), report: false);
        }

        $result = $this->evaluate($this->authorization);

        return $result instanceof Response ? $result : ($result ? Response::allow() : Response::deny());
    }

    /**
     * The policy's message to show on a disabled action, or null to hide it.
     */
    protected function getAuthorizationTooltip(): ?string
    {
        if (! $this->evaluate($this->hasAuthorizationTooltip)) {
            return null;
        }

        $response = $this->inspectAuthorization();

        return $response->allowed() ? null : $response->message();
    }

    public function isDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDisabled) || $this->getAuthorizationTooltip() !== null;
    }

    public function isHiddenDynamically(): bool
    {
        // In this order, so a policy only sees an action that could show.
        return parent::isHiddenDynamically()
            || (! $this->isAuthorized() && $this->getAuthorizationTooltip() === null)
            || ($this->url !== null && $this->getUrl() === null);
    }

    /**
     * Whether this action may run right now. Read prepared state before asking.
     */
    public function canRun(): bool
    {
        $form = $this->getForm();

        // A row key the browser made up builds a row with no state, and no record.
        if ($this->isDisabled() || ! $this->isShown() || ! Arr::has($form->getState(), $this->container)) {
            return false;
        }

        for ($parent = $this->getParent(); $parent !== null; $parent = $parent->getParent()) {
            if ($parent instanceof Field && (! $parent->acceptsInput() || $parent->isDisabledInState())) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, Component>
     */
    public function getModalComponents(): array
    {
        return array_map(fn (Component $component): Component => clone $component, $this->evaluate($this->modalComponents));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFillData(): ?array
    {
        return $this->evaluate($this->fillUsing);
    }

    public function needsConfirmation(): bool
    {
        return (bool) $this->evaluate($this->requiresConfirmation);
    }

    public function hasModalForm(): bool
    {
        return $this->modalComponents !== [];
    }

    /**
     * @param  array<string, mixed>  $parentData  The state the browser posted to the form this action is in.
     */
    public function makeForm(array $parentData): ActionForm
    {
        return ActionForm::for($this, $parentData);
    }

    /**
     * Check the modal's values and do the work. Call it inside a transaction.
     *
     * @internal
     *
     * @param  array<string, mixed>  $parentData
     * @param  array<string, mixed>  $input  What the browser posted for the modal.
     * @return mixed What the closure returned, for the browser.
     */
    public function run(array $parentData, array $input): mixed
    {
        $this->ensureWithinRateLimit();

        $data = $this->hasModalForm() ? $this->makeForm($parentData)->validate([StatePath::ROOT => $input]) : [];

        $result = $this->evaluate($this->action, ['data' => $data]);
        $notificationTitle = $this->evaluate($this->successNotificationTitle, ['data' => $data]);

        if ($notificationTitle !== null) {
            Notification::make()->title($notificationTitle)->success()->send();
        }

        return $result;
    }

    /**
     * Here, so every place that runs an action counts it: forms, tables and panels.
     */
    protected function ensureWithinRateLimit(): void
    {
        $attempts = $this->evaluate($this->rateLimit);

        if ($attempts === null) {
            return;
        }

        $request = request();
        $key = 'xelqun-action:'.sha1(implode('|', [$request->user()?->getAuthIdentifier() ?? $request->ip(), $this->getForm()::class, $this->getKey()]));

        if (RateLimiter::tooManyAttempts($key, $attempts)) {
            abort(429, headers: ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key);
    }

    public function evaluate(mixed $value, array $parameters = []): mixed
    {
        return parent::evaluate($value, ['record' => $this->getRecord(), ...$parameters]);
    }

    protected function getProps(): array
    {
        $label = $this->evaluate($this->label) ?? Str::headline($this->name);
        $authorizationTooltip = $this->getAuthorizationTooltip();

        return [
            'label' => $label,
            'style' => $this->style,
            'disabled' => $this->isDisabled(),
            'confirmation' => $this->needsConfirmation(),
            'hasForm' => $this->hasModalForm(),
            'heading' => $this->evaluate($this->modalHeading) ?? $label,
            'description' => $this->evaluate($this->modalDescription),
            'submitLabel' => $this->evaluate($this->modalSubmitActionLabel),
            'cancelLabel' => $this->evaluate($this->modalCancelActionLabel),
            ...($this->url === null ? [] : [
                'url' => $this->getUrl(),
                'openInNewTab' => (bool) $this->evaluate($this->opensUrlInNewTab),
            ]),
            ...$this->evaluateConfigured([
                'icon' => $this->icon,
                'color' => $this->color,
                'tooltip' => $authorizationTooltip ?? $this->tooltip,
                'badge' => $this->badge,
                'badgeColor' => $this->badgeColor,
                'size' => $this->size,
                'width' => $this->modalWidth,
                'modalIcon' => $this->modalIcon,
                'modalIconColor' => $this->modalIconColor,
                'alignment' => $this->modalAlignment,
                'footerAlignment' => $this->modalFooterActionsAlignment,
            ]),
            ...($this->evaluate($this->isOutlined) ? ['outlined' => true] : []),
            ...($this->evaluate($this->isSlideOver) ? ['slideOver' => true] : []),
            ...(($keys = $this->evaluate($this->keyBindings)) === [] ? [] : ['keyBindings' => array_values($keys)]),
            ...($this->evaluate($this->hasModalCloseButton) ? [] : ['closeButton' => false]),
            ...($this->evaluate($this->closesModalByClickingAway) ? [] : ['closeOnClickAway' => false]),
        ];
    }
}
