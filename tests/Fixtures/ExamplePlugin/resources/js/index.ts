import type { XelqunPlugin } from '@xelqun/forms';

export function reviewsPlugin(): XelqunPlugin {
    return {
        id: 'acme/reviews',
        components: {
            'acme/rating': () => import('./components/RatingField.vue'),
            'acme/reviewer': () => import('./components/ReviewerField.vue'),
            'acme/receipt': () => import('./components/ReceiptField.vue'),
        },
    };
}
