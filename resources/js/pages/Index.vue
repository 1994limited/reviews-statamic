<!--
    Content → Reviews: the moderation queue. Tabs for each status, with
    Pending first while anything waits, a search, a star filter, and
    approving or rejecting several at once.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Header, Input, Panel, Select, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows } from '@statamic/cms/ui';
import Stars from '../components/Stars.vue';
import StatusBadge from '../components/StatusBadge.vue';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Button, Head, Header, Input, Link, Panel, Select, Stars, StatusBadge, Table, TableCell, TableColumn, TableColumns, TableRow, TableRows },

    props: {
        reviews: { type: Array, required: true },
        counts: { type: Object, required: true },
        status: { type: String, required: true },
        statuses: { type: Array, required: true },
        search: { type: String, default: null },
        rating: { type: Number, default: null },
        entry: { type: Object, default: null },
        pagination: { type: Object, required: true },
        can: { type: Object, required: true },
        urls: { type: Object, required: true },
    },

    data() {
        return { query: this.search ?? '', stars: this.rating ? String(this.rating) : 'all', selected: [], timer: null, busy: false };
    },

    computed: {
        icon: () => icon,

        tabs() {
            const total = Object.values(this.counts).reduce((sum, n) => sum + n, 0);

            return [...this.statuses.map((s) => ({ ...s, count: this.counts[s.value] ?? 0 })), { value: 'all', label: __('All'), count: total }];
        },

        ratingOptions() {
            return [{ value: 'all', label: __('Any rating') }, ...[5, 4, 3, 2, 1].map((n) => ({ value: String(n), label: n === 1 ? __('1 star') : __(':count stars', { count: n }) }))];
        },

        allSelected() {
            return this.reviews.length > 0 && this.selected.length === this.reviews.length;
        },

        filtered() {
            return Boolean(this.search || this.rating || this.entry);
        },
    },

    watch: {
        query() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.reload(), 300);
        },

        stars() {
            this.reload();
        },

        reviews() {
            this.selected = this.selected.filter((id) => this.reviews.some((r) => r.id === id));
        },
    },

    methods: {
        params(overrides = {}) {
            const params = { status: this.status };
            if (this.query) params.search = this.query;
            if (this.stars !== 'all') params.rating = this.stars;
            if (this.entry) params.entry = this.entry.id;

            return { ...params, ...overrides };
        },

        url(overrides = {}) {
            const query = new URLSearchParams(Object.entries(this.params(overrides)).filter(([, v]) => v !== null && v !== undefined)).toString();

            return this.urls.index + (query ? `?${query}` : '');
        },

        reload() {
            router.get(this.urls.index, this.params(), { preserveState: true, replace: true });
        },

        toggleAll() {
            this.selected = this.allSelected ? [] : this.reviews.map((r) => r.id);
        },

        bulk(action) {
            if (!this.selected.length) return;

            if (action === 'delete' && !confirm(__('Delete :count reviews? This can’t be undone.', { count: this.selected.length }))) {
                return;
            }

            this.busy = true;
            router.post(this.urls.bulk, { ids: this.selected, action }, {
                preserveScroll: true,
                onFinish: () => (this.busy = false),
                onSuccess: () => (this.selected = []),
            });
        },
    },
};
</script>

<template>
    <Head :title="__('Reviews')" />

    <div class="mx-auto max-w-6xl">
        <Header :title="__('Reviews')" :icon="icon" />

        <nav class="mb-4 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700!" :aria-label="__('Status')">
            <Link
                v-for="tab in tabs"
                :key="tab.value"
                :href="url({ status: tab.value, page: null })"
                class="-mb-px flex items-center gap-2 border-b-2 px-3 py-2 text-sm"
                :class="tab.value === status ? 'border-blue-600 font-medium text-gray-900 dark:text-white!' : 'border-transparent text-gray-600 hover:text-gray-900! dark:text-gray-400! dark:hover:text-white!'"
                :aria-current="tab.value === status ? 'page' : null"
            >
                {{ tab.label }}
                <span class="rounded-full px-1.5 text-xs tabular-nums" :class="tab.value === 'pending' && tab.count ? 'bg-amber-100 text-amber-900 dark:bg-amber-900/50! dark:text-amber-100!' : 'bg-gray-100 text-gray-700 dark:bg-gray-800! dark:text-gray-300!'">{{ tab.count }}</span>
            </Link>
        </nav>

        <div class="mb-3 flex flex-col gap-3 sm:flex-row">
            <div class="grow">
                <Input v-model="query" icon="magnifying-glass" :placeholder="__('Search the reviews, names and emails')" clearable :aria-label="__('Search')" />
            </div>
            <div class="sm:w-44">
                <Select v-model="stars" :options="ratingOptions" option-label="label" option-value="value" :aria-label="__('Rating')" />
            </div>
        </div>

        <div v-if="entry" class="mb-3 flex items-center gap-2 text-sm">
            <span class="text-gray-600 dark:text-gray-400!">{{ __('Reviews of') }}</span>
            <span class="font-medium">{{ entry.title }}</span>
            <Link :href="url({ entry: null, page: null })" class="text-blue-700 hover:underline! dark:text-blue-400!">{{ __('Show all') }}</Link>
        </div>

        <div v-if="selected.length" class="mb-3 flex flex-wrap items-center gap-2 rounded-lg bg-gray-100 px-3 py-2 text-sm dark:bg-gray-800!">
            <span class="mr-2">{{ __(':count selected', { count: selected.length }) }}</span>
            <template v-if="can.moderate">
                <Button size="sm" variant="primary" :disabled="busy" :text="__('Approve')" @click="bulk('approved')" />
                <Button size="sm" :disabled="busy" :text="__('Reject')" @click="bulk('rejected')" />
                <Button size="sm" :disabled="busy" :text="__('Spam')" @click="bulk('spam')" />
            </template>
            <Button v-if="can.delete" size="sm" variant="danger" :disabled="busy" :text="__('Delete')" @click="bulk('delete')" />
        </div>

        <Panel>
            <div v-if="reviews.length" class="overflow-x-auto">
                <Table>
                    <TableColumns>
                        <TableColumn v-if="can.moderate || can.delete" class="w-8">
                            <input type="checkbox" :checked="allSelected" :aria-label="__('Select all')" @change="toggleAll" />
                        </TableColumn>
                        <TableColumn>{{ __('Review') }}</TableColumn>
                        <TableColumn>{{ __('Of') }}</TableColumn>
                        <TableColumn>{{ __('Status') }}</TableColumn>
                        <TableColumn class="text-right">{{ __('Written') }}</TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="review in reviews" :key="review.id">
                            <TableCell v-if="can.moderate || can.delete">
                                <input v-model="selected" type="checkbox" :value="review.id" :aria-label="__('Select')" />
                            </TableCell>
                            <TableCell class="min-w-72">
                                <div class="flex items-center gap-2">
                                    <Stars :rating="review.rating" />
                                    <Link :href="review.url" class="font-medium hover:underline!">{{ review.title || __('(no title)') }}</Link>
                                </div>
                                <p v-if="review.excerpt" class="mt-0.5 line-clamp-2 text-sm text-gray-700 dark:text-gray-300!">{{ review.excerpt }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-gray-600 dark:text-gray-400!">
                                    <span>{{ review.name }}</span>
                                    <span v-if="review.verified" class="text-green-700 dark:text-green-400!">✓ {{ __('Verified purchase') }}</span>
                                    <span v-if="review.photos">{{ review.photos === 1 ? __('1 photo') : __(':count photos', { count: review.photos }) }}</span>
                                    <span v-if="review.has_reply">{{ __('Replied') }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Link :href="url({ entry: review.entry_id, status: 'all', page: null })" class="hover:underline!">{{ review.entry_title }}</Link>
                            </TableCell>
                            <TableCell><StatusBadge :status="review.status" :label="review.status_label" /></TableCell>
                            <TableCell class="text-right whitespace-nowrap text-gray-600 dark:text-gray-400!" :title="review.created_at">{{ review.created_ago }}</TableCell>
                        </TableRow>
                    </TableRows>
                </Table>
            </div>

            <div v-else class="px-6 py-10 text-center">
                <p v-if="filtered" class="text-gray-600 dark:text-gray-400!">{{ __('No reviews match.') }}</p>
                <p v-else-if="status === 'pending'" class="text-gray-600 dark:text-gray-400!">{{ __('All caught up: no reviews are waiting.') }}</p>
                <template v-else>
                    <p class="font-medium">{{ __('No reviews here yet') }}</p>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400!">{{ __('Reviews appear here as visitors write them with the review form.') }}</p>
                </template>
            </div>
        </Panel>

        <nav v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between text-sm" :aria-label="__('Pages')">
            <Link v-if="pagination.page > 1" :href="url({ page: pagination.page - 1 })" class="hover:underline!">← {{ __('Previous') }}</Link>
            <span v-else />
            <span class="text-gray-600 dark:text-gray-400!">{{ __(':page of :last', { page: pagination.page, last: pagination.last_page }) }} · {{ __(':total reviews', { total: pagination.total }) }}</span>
            <Link v-if="pagination.page < pagination.last_page" :href="url({ page: pagination.page + 1 })" class="hover:underline!">{{ __('Next') }} →</Link>
            <span v-else />
        </nav>
    </div>
</template>
