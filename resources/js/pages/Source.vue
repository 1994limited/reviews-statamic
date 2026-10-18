<!--
    One sync source: the platform's keys, where its company reviews go, and
    how often it syncs.
-->
<script>
import { Head, Link, router, useForm } from '@statamic/cms/inertia';
import { Alert, Button, Field, Header, Input, Panel, Switch } from '@statamic/cms/ui';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Alert, Button, Field, Head, Header, Input, Link, Panel, Switch },

    props: {
        source: { type: Object, required: true },
        platform: { type: Object, required: true },
        urls: { type: Object, required: true },
    },

    setup(props) {
        const form = useForm({
            type: props.source.type,
            name: props.source.name,
            settings: { ...props.source.settings },
            enabled: props.source.enabled,
            count_in_summary: props.source.count_in_summary,
            frequency: props.source.frequency,
            business_entry: props.source.business_entry,
        });

        return { form, icon };
    },

    computed: {
        title() {
            return this.source.id ? this.source.name : __('New :platform source', { platform: this.platform.label });
        },
    },

    methods: {
        save() {
            this.source.id ? this.form.patch(this.urls.save, { preserveScroll: true }) : this.form.post(this.urls.save, { preserveScroll: true });
        },

        remove() {
            if (confirm(__('Remove this source? Its reviews stay.'))) {
                router.delete(this.urls.destroy);
            }
        },

        hasSaved(handle) {
            return this.source.saved_secrets.includes(handle);
        },
    },
};
</script>

<template>
    <Head :title="title" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-2 text-sm">
            <Link :href="urls.index" class="text-gray-600 hover:underline! dark:text-gray-400!">← {{ __('Sync') }}</Link>
        </div>

        <Header :title="title" :icon="icon">
            <Button v-if="urls.destroy" variant="danger" icon="trash" :text="__('Remove')" @click="remove" />
            <Button variant="primary" :disabled="form.processing" :text="__('Save')" @click="save" />
        </Header>

        <Alert v-if="source.last_error" variant="error" class="mb-4">{{ source.last_error }}</Alert>

        <Panel>
            <div class="space-y-6 p-5">
                <Field :label="__('Name')" :error="form.errors.name">
                    <Input v-model="form.name" />
                </Field>

                <Field
                    v-for="field in platform.fields"
                    :key="field.handle"
                    :label="field.label"
                    :instructions="field.help + (field.secret ? ' ' + __('Best as an environment variable, such as $MY_KEY.') : '') + (field.secret && hasSaved(field.handle) ? ' ' + __('Leave it empty to keep the one saved.') : '')"
                    :error="form.errors['settings.' + field.handle]"
                >
                    <Input v-model="form.settings[field.handle]" :type="field.secret ? 'password' : 'text'" autocomplete="off" />
                </Field>

                <Alert v-if="platform.attribution" variant="default">{{ platform.attribution }}</Alert>

                <Field :label="__('Reviews of the business go on')" :instructions="__('Company reviews aren’t of a product: the ID of the entry they belong to, or they’re left out.')">
                    <Input v-model="form.business_entry" :placeholder="__('Entry ID')" />
                </Field>

                <Field v-if="platform.schema_allowed" :label="__('Count towards ratings and schema')" :instructions="__(':platform allows its product reviews to be marked up on your own pages.', { platform: platform.label })" :error="form.errors.count_in_summary">
                    <Switch v-model="form.count_in_summary" />
                </Field>

                <Field :label="__('Hours between syncs')">
                    <Input v-model="form.frequency" type="number" min="1" max="720" />
                </Field>

                <Field :label="__('On')">
                    <Switch v-model="form.enabled" />
                </Field>
            </div>
        </Panel>
    </div>
</template>
