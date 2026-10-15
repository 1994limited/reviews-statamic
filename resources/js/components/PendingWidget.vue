<!--
    The dashboard's "Reviews waiting" widget: the oldest reviews waiting for
    an editor.
-->
<script>
import { Widget } from '@statamic/cms/ui';
import Stars from './Stars.vue';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Stars, Widget },

    props: {
        title: { type: String, required: true },
        total: { type: Number, required: true },
        reviews: { type: Array, required: true },
        url: { type: String, required: true },
        empty: { type: String, required: true },
    },

    computed: {
        icon: () => icon,
    },
};
</script>

<template>
    <Widget :title="title" :icon="icon" :href="url">
        <div class="px-4 py-3 text-sm">
            <p v-if="!reviews.length" class="text-gray-500">{{ empty }}</p>
            <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800!">
                <li v-for="review in reviews" :key="review.id" class="py-1.5">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="flex min-w-0 items-center gap-2">
                            <Stars :rating="review.rating" />
                            <a :href="review.url" class="truncate font-medium hover:underline!">{{ review.title || review.entry_title }}</a>
                        </span>
                        <span class="shrink-0 text-xs text-gray-500">{{ review.created_ago }}</span>
                    </div>
                    <div class="truncate text-xs text-gray-500">{{ review.name }} · {{ review.entry_title }}</div>
                </li>
            </ul>
            <a v-if="total > reviews.length" :href="url" class="mt-1 inline-block text-xs text-blue-700 hover:underline! dark:text-blue-400!">{{ __('All :count waiting', { count: total }) }} →</a>
        </div>
    </Widget>
</template>
