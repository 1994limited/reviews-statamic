<!--
    Reviews → Sync: the platforms reviews are synced from.
-->
<script>
import { Head, Link, router } from '@statamic/cms/inertia';
import { Button, Dropdown, DropdownItem, DropdownMenu, Header, Panel } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Button, Dropdown, DropdownItem, DropdownMenu, Head, Header, Link, Panel },

    props: {
        sources: { type: Array, required: true },
        platforms: { type: Array, required: true },
        urls: { type: Object, required: true },
    },

    setup() {
        return { icon };
    },

    data() {
        return { busy: null };
    },

    methods: {
        run(source) {
            this.busy = source.id;
            router.post(source.run_url, {}, { preserveScroll: true, onFinish: () => (this.busy = null) });
        },

        when(date) {
            return date ? new Date(date).toLocaleString() : __('Not yet');
        },
    },
};
</script>

<template>
    <Head :title="__('Sync')" />

    <div class="mx-auto max-w-4xl">
        <div class="mb-2 text-sm">
            <Link :href="urls.reviews" class="text-gray-600 hover:underline! dark:text-gray-400!">← {{ __('Reviews') }}</Link>
        </div>

        <Header :title="__('Sync')" :icon="icon">
            <Dropdown>
                <template #trigger>
                    <Button variant="primary" icon-append="chevron-down" :text="__('New source')" />
                </template>
                <DropdownMenu>
                    <DropdownItem v-for="platform in platforms" :key="platform.value" :text="platform.label" :href="platform.create_url" />
                </DropdownMenu>
            </Dropdown>
        </Header>

        <p class="mb-4 text-sm text-gray-700 dark:text-gray-300!">
            {{ __('Reviews fetched from other platforms on a schedule, shown on your pages with where they came from. They don’t count towards your ratings or go in your schema, unless the platform allows it.') }}
        </p>

        <Panel v-if="sources.length">
            <div class="divide-y divide-gray-200 dark:divide-gray-700!">
                <div v-for="source in sources" :key="source.id" class="flex flex-wrap items-center gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <Link :href="source.edit_url" class="font-medium hover:underline!">{{ source.name }}</Link>
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400!">{{ source.platform }}<template v-if="!source.enabled"> · {{ __('Off') }}</template></span>
                        <p class="text-sm text-gray-600 dark:text-gray-400!">{{ __('Last synced') }}: {{ when(source.last_synced) }}</p>
                        <p v-if="source.last_error" class="text-sm text-red-700 dark:text-red-400!">{{ source.last_error }}</p>
                    </div>
                    <Button size="sm" :disabled="busy === source.id" :text="busy === source.id ? __('Syncing…') : __('Sync now')" @click="run(source)" />
                </div>
            </div>
        </Panel>
        <Panel v-else>
            <p class="p-6 text-center text-sm text-gray-600 dark:text-gray-400!">{{ __('No sources yet.') }}</p>
        </Panel>

        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400!">
            {{ __('Sources sync from Laravel’s scheduler, hourly, each once its own interval has passed: make sure php artisan schedule:run runs every minute. Google reviews can’t be synced: Google’s terms don’t allow keeping them.') }}
        </p>
    </div>
</template>
