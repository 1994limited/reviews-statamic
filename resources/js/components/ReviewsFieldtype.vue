<!--
    The Reviews field in an entry's blueprint: its average rating and how
    many reviews it has, with links to them. Read only; it saves nothing.
-->
<script>
import { FieldtypeMixin } from '@statamic/cms';
import Stars from './Stars.vue';

export default {
    mixins: [FieldtypeMixin],

    components: { Stars },

    computed: {
        info() {
            return this.meta ?? { exists: false };
        },
    },
};
</script>

<template>
    <div class="text-sm">
        <p v-if="!info.exists" class="text-gray-500">{{ __('Reviews show here once the entry is saved.') }}</p>
        <template v-else>
            <div v-if="info.count" class="flex flex-wrap items-center gap-2">
                <Stars :rating="info.average" />
                <span class="font-medium">{{ info.average }}</span>
                <span class="text-gray-600 dark:text-gray-400!">{{ info.count === 1 ? __('from 1 review') : __('from :count reviews', { count: info.count }) }}</span>
            </div>
            <p v-else class="text-gray-600 dark:text-gray-400!">{{ __('No approved reviews yet.') }}</p>
            <div class="mt-1 flex flex-wrap gap-x-3">
                <a :href="info.url" class="text-blue-700 hover:underline! dark:text-blue-400!">{{ __('See the reviews') }}</a>
                <a v-if="info.pending" :href="info.pending_url" class="text-amber-700 hover:underline! dark:text-amber-400!">{{ info.pending === 1 ? __('1 waiting') : __(':count waiting', { count: info.pending }) }}</a>
            </div>
        </template>
    </div>
</template>
