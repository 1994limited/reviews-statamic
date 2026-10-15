import Index from './pages/Index.vue';
import Show from './pages/Show.vue';
import PendingWidget from './components/PendingWidget.vue';
import ReviewsFieldtype from './components/ReviewsFieldtype.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('reviews::Index', Index);
    Statamic.$inertia.register('reviews::Show', Show);
    Statamic.$components.register('reviews-pending-widget', PendingWidget);
    Statamic.$components.register('reviews-fieldtype', ReviewsFieldtype);
});
