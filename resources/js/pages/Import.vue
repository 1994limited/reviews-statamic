<!--
    Reviews → Import: upload another platform's export, see what would be
    added, then add it.
-->
<script>
import { Head, Link, useForm } from '@statamic/cms/inertia';
import { Alert, Button, Field, Header, Input, Panel, Select, Switch } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Alert, Button, Field, Head, Header, Input, Link, Panel, Select, Switch },

    props: {
        formats: { type: Array, required: true },
        sites: { type: Array, required: true },
        options: { type: Object, required: true },
        business_title: { type: String, default: null },
        state: { type: Object, required: true },
        urls: { type: Object, required: true },
        max_kilobytes: { type: Number, required: true },
    },

    setup(props) {
        const form = useForm({ file: null, ...props.options });

        return { form, icon };
    },

    computed: {
        formatOptions() {
            return [{ value: '', label: __('Work it out from the columns') }, ...this.formats];
        },

        statusOptions() {
            return [
                { value: 'keep', label: __('Approved, if the platform showed them') },
                { value: 'pending', label: __('Waiting for an editor') },
            ];
        },

        previewing() {
            return Boolean(this.state.token);
        },
    },

    methods: {
        choose(event) {
            this.form.file = event.target.files[0] ?? null;
        },

        preview() {
            this.form.post(this.urls.preview, { forceFormData: true, preserveScroll: true });
        },

        run() {
            this.form.transform((data) => ({ ...data, file: null, token: this.state.token })).post(this.urls.run, { preserveScroll: true });
        },
    },
};
</script>

<template>
    <Head :title="__('Import reviews')" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-2 text-sm">
            <Link :href="urls.reviews" class="text-gray-600 hover:underline! dark:text-gray-400!">← {{ __('Reviews') }}</Link>
        </div>

        <Header :title="__('Import reviews')" :icon="icon" />

        <Panel v-if="state.done">
            <div class="space-y-3 p-5">
                <p class="font-medium">{{ __n(':count review added.|:count reviews added.', state.done.added) }}</p>
                <ul class="list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300!">
                    <li v-if="state.done.unchanged">{{ __n(':count was here already.|:count were here already.', state.done.unchanged) }}</li>
                    <li v-if="state.done.unmatched.length">{{ __n(':count matched nothing, and was left out.|:count matched nothing, and were left out.', state.done.unmatched.length) }}</li>
                    <li v-if="state.done.skipped">{{ __n(':count row had no rating, and was skipped.|:count rows had no rating, and were skipped.', state.done.skipped) }}</li>
                    <li v-if="state.done.failed" class="text-red-700 dark:text-red-400!">{{ __n(':count couldn’t be saved: see the logs.|:count couldn’t be saved: see the logs.', state.done.failed) }}</li>
                </ul>
                <div class="flex gap-2">
                    <Link :href="urls.reviews"><Button variant="primary" :text="__('See the reviews')" /></Link>
                    <Link :href="urls.index"><Button :text="__('Import another file')" /></Link>
                </div>
            </div>
        </Panel>

        <template v-else>
            <p class="mb-4 text-sm text-gray-700 dark:text-gray-300!">
                {{ __('Add reviews from a CSV or JSON file another platform exported: Yotpo, Judge.me, REVIEWS.io, Shopify Product Reviews, Trustpilot, WooCommerce, Reviews itself, or any file with a rating column. Nothing is added until you’ve seen what would be.') }}
            </p>

            <Panel v-if="previewing">
                <div class="space-y-3 p-5">
                    <Alert v-if="state.error" variant="error">{{ state.error }}</Alert>
                    <template v-else>
                        <p class="font-medium">{{ __(':file, read as :format.', { file: state.filename, format: state.format }) }}</p>
                        <ul class="list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300!">
                            <li>{{ __n(':count review would be added.|:count reviews would be added.', state.counts.matched) }}</li>
                            <li v-if="state.counts.existing">{{ __n(':count is here already.|:count are here already.', state.counts.existing) }}</li>
                            <li v-if="state.counts.unpublished">{{ __n(':count the platform wasn’t showing is left out.|:count the platform wasn’t showing are left out.', state.counts.unpublished) }}</li>
                            <li v-if="state.skipped">{{ __n(':count row has no rating, and is skipped.|:count rows have no rating, and are skipped.', state.skipped) }}</li>
                        </ul>
                        <div v-if="state.counts.unmatched.length" class="text-sm">
                            <p class="mb-1 font-medium">{{ __('Nothing on this site matches:') }}</p>
                            <p class="mb-1 text-gray-600 dark:text-gray-400!">{{ __('Reviews are matched to entries by ID, SKU (an entry’s sku field), slug, page address or exact title.') }}</p>
                            <ul class="list-disc ps-5 font-mono text-xs">
                                <li v-for="item in state.counts.unmatched.slice(0, 20)" :key="item">{{ item }}</li>
                            </ul>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="primary" :disabled="!state.counts.matched || form.processing" :text="__n('Import :count review|Import :count reviews', state.counts.matched)" @click="run" />
                            <Link :href="urls.index"><Button :text="__('Cancel')" /></Link>
                        </div>
                    </template>
                </div>
            </Panel>

            <Panel v-else>
                <div class="space-y-6 p-5">
                    <Field :label="__('File')" :instructions="__('A CSV or JSON file, up to :size MB. For bigger files, use php please reviews:import.', { size: Math.round(max_kilobytes / 1024) })" :error="form.errors.file">
                        <input type="file" accept=".csv,.json,.txt,text/csv,application/json" class="text-sm" @change="choose" />
                    </Field>

                    <Field :label="__('Format')">
                        <Select v-model="form.format" :options="formatOptions" option-label="label" option-value="value" />
                    </Field>

                    <Field :label="__('Count them as this site’s own reviews')" :instructions="__('For reviews your customers wrote for you on a platform you’re leaving. They then count towards ratings and go in your schema. Leave this off for reviews gathered elsewhere.')">
                        <Switch v-model="form.counted" />
                    </Field>

                    <Field :label="__('Status')">
                        <Select v-model="form.status" :options="statusOptions" option-label="label" option-value="value" />
                    </Field>

                    <Field :label="__('Include reviews the platform wasn’t showing')" :instructions="__('Added as rejected, so they’re on record.')">
                        <Switch v-model="form.include_unpublished" />
                    </Field>

                    <Field :label="__('Reviews of the business go on')" :instructions="__('Company reviews aren’t of a product: the ID of the entry they belong to (a reviews page, say), or they’re left out.')">
                        <Input v-model="form.business_entry" :placeholder="__('Entry ID')" />
                    </Field>

                    <Field v-if="sites.length" :label="__('Site')">
                        <Select v-model="form.site" :options="[{ value: '', label: __('The entry’s own') }, ...sites]" option-label="label" option-value="value" />
                    </Field>

                    <Button variant="primary" :disabled="!form.file || form.processing" :text="__('Preview')" @click="preview" />
                </div>
            </Panel>
        </template>
    </div>
</template>
