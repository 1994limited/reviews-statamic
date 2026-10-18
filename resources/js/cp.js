import Index from './pages/Index.vue';
import Show from './pages/Show.vue';
import Import from './pages/Import.vue';
import Sources from './pages/Sources.vue';
import Source from './pages/Source.vue';
import PendingWidget from './components/PendingWidget.vue';
import ReviewsFieldtype from './components/ReviewsFieldtype.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('reviews::Index', Index);
    Statamic.$inertia.register('reviews::Show', Show);
    Statamic.$inertia.register('reviews::Import', Import);
    Statamic.$inertia.register('reviews::Sources', Sources);
    Statamic.$inertia.register('reviews::Source', Source);
    Statamic.$components.register('reviews-pending-widget', PendingWidget);
    Statamic.$components.register('reviews-fieldtype', ReviewsFieldtype);
});
