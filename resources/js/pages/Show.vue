<!--
    One review: what they wrote, who they are, and (with permission)
    approving, rejecting, replying in public and keeping a private note.
-->
<script>
import { Head, Link, router, useForm } from '@statamic/cms/inertia';
import { Button, Field, Header, Panel, Textarea } from '@statamic/cms/ui';
import Stars from '../components/Stars.vue';
import StatusBadge from '../components/StatusBadge.vue';
import icon from '../../brand/nav-icon.svg?raw';

export default {
    components: { Button, Field, Head, Header, Link, Panel, Stars, StatusBadge, Textarea },

    props: {
        review: { type: Object, required: true },
        statuses: { type: Array, required: true },
        can: { type: Object, required: true },
        urls: { type: Object, required: true },
    },

    setup(props) {
        const reply = useForm({ reply: props.review.reply ?? '' });
        const note = useForm({ note: props.review.note ?? '' });

        return { replyForm: reply, noteForm: note };
    },

    data() {
        return { busy: false, lightbox: null };
    },

    computed: {
        icon: () => icon,

        date() {
            return this.review.created_at ? new Date(this.review.created_at).toLocaleString() : '';
        },
    },

    methods: {
        setStatus(status) {
            let note = null;

            if (status === 'rejected') {
                note = prompt(__('Why? Only editors see this (optional).'), this.review.note ?? '');

                if (note === null) return;
            }

            this.busy = true;
            router.post(this.urls.status, { status, note }, { preserveScroll: true, onFinish: () => (this.busy = false) });
        },

        saveReply() {
            this.replyForm.post(this.urls.reply, { preserveScroll: true });
        },

        saveNote() {
            this.noteForm.post(this.urls.note, { preserveScroll: true });
        },

        destroy() {
            if (!confirm(__('Delete this review? This can’t be undone.'))) return;

            router.delete(this.urls.destroy);
        },
    },
};
</script>

<template>
    <Head :title="review.title || __('Review')" />

    <div class="mx-auto max-w-5xl">
        <div class="mb-2 text-sm">
            <Link :href="urls.index" class="text-gray-600 hover:underline! dark:text-gray-400!">← {{ __('Reviews') }}</Link>
        </div>

        <Header :icon="icon">
            <template #title>
                <span class="flex flex-wrap items-center gap-3">
                    <span>{{ review.title || __('Review by :name', { name: review.name }) }}</span>
                    <StatusBadge :status="review.status" :label="review.status_label" />
                </span>
            </template>

            <template v-if="can.moderate">
                <Button v-if="review.status !== 'approved'" variant="primary" icon="checkmark" :disabled="busy" :text="__('Approve')" @click="setStatus('approved')" />
                <Button v-if="review.status !== 'rejected'" :disabled="busy" :text="__('Reject')" @click="setStatus('rejected')" />
                <Button v-if="review.status !== 'spam'" :disabled="busy" :text="__('Spam')" @click="setStatus('spam')" />
                <Button v-if="review.status !== 'pending'" :disabled="busy" :text="__('Back to pending')" @click="setStatus('pending')" />
            </template>
            <Button v-if="can.delete" variant="danger" icon="trash" :text="__('Delete')" @click="destroy" />
        </Header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <Panel>
                    <div class="space-y-4 p-5">
                        <div class="flex flex-wrap items-center gap-3">
                            <Stars :rating="review.rating" size="lg" />
                            <span class="text-sm text-gray-600 dark:text-gray-400!">{{ __(':rating out of 5', { rating: review.rating }) }}</span>
                            <span v-if="review.verified" class="text-sm text-green-700 dark:text-green-400!">✓ {{ __('Verified purchase') }}</span>
                        </div>

                        <dl v-if="review.ratings.length" class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <div v-for="criterion in review.ratings" :key="criterion.label" class="flex items-center gap-2">
                                <dt class="text-gray-600 dark:text-gray-400!">{{ criterion.label }}</dt>
                                <dd><Stars :rating="criterion.rating" /></dd>
                            </div>
                        </dl>

                        <h2 v-if="review.title" class="text-lg font-semibold">{{ review.title }}</h2>
                        <p v-if="review.body" class="whitespace-pre-line">{{ review.body }}</p>
                        <p v-else class="italic text-gray-500">{{ __('A rating without words.') }}</p>

                        <div v-if="review.photos.length" class="flex flex-wrap gap-3">
                            <a v-for="(photo, index) in review.photos" :key="index" :href="photo.url" target="_blank" rel="noopener" class="group relative block">
                                <img :src="photo.url" :alt="__('Photo :n', { n: index + 1 })" class="h-28 w-28 rounded-md object-cover ring-1 ring-gray-200 dark:ring-gray-700!" />
                                <span v-if="photo.pending" class="absolute bottom-1 left-1 rounded bg-black/70 px-1.5 text-xs text-white">{{ __('Private until approved') }}</span>
                            </a>
                        </div>
                    </div>
                </Panel>

                <Panel :heading="__('Your public reply')">
                    <form v-if="can.reply" class="space-y-3 p-4" @submit.prevent="saveReply">
                        <Field :instructions="review.status === 'approved' ? __('Shown under the review. The reviewer is emailed when you reply, if that’s turned on in the settings.') : __('Shown under the review once it’s approved.')" :error="replyForm.errors.reply">
                            <Textarea v-model="replyForm.reply" :limit="5000" elastic :aria-label="__('Reply')" />
                        </Field>
                        <div class="flex items-center gap-3">
                            <Button type="submit" variant="primary" :loading="replyForm.processing" :text="review.reply ? __('Update reply') : __('Reply')" />
                            <span v-if="review.replied_by" class="text-xs text-gray-600 dark:text-gray-400!">{{ __('Last replied by :name', { name: review.replied_by }) }}</span>
                        </div>
                    </form>
                    <p v-else-if="review.reply" class="whitespace-pre-line p-4">{{ review.reply }}</p>
                    <p v-else class="p-4 text-sm text-gray-500">{{ __('No reply.') }}</p>
                </Panel>
            </div>

            <div class="space-y-6">
                <Panel :heading="__('Details')">
                    <dl class="space-y-3 p-4 text-sm">
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Of') }}</dt>
                            <dd>
                                <a v-if="review.entry_edit_url" :href="review.entry_edit_url" class="font-medium hover:underline!">{{ review.entry_title }}</a>
                                <span v-else>{{ review.entry_title }}</span>
                                <span class="block"><Link :href="review.entry_reviews_url" class="text-blue-700 hover:underline! dark:text-blue-400!">{{ __('All its reviews') }}</Link></span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('By') }}</dt>
                            <dd>
                                {{ review.name }}
                                <a v-if="review.email" :href="`mailto:${review.email}`" class="block text-gray-600 hover:underline! dark:text-gray-400!">{{ review.email }}</a>
                                <a v-if="review.user" :href="review.user.url" class="block text-gray-600 hover:underline! dark:text-gray-400!">{{ __('User: :name', { name: review.user.name }) }}</a>
                            </dd>
                        </div>
                        <div v-if="review.order">
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Order') }}</dt>
                            <dd><a v-if="review.order_url" :href="review.order_url" class="hover:underline!">{{ review.order }}</a><span v-else>{{ review.order }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Written') }}</dt>
                            <dd>{{ date }}<span v-if="review.site_name" class="text-gray-600 dark:text-gray-400!"> · {{ review.site_name }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-gray-600 dark:text-gray-400!">{{ __('Found helpful') }}</dt>
                            <dd>{{ review.helpful === 1 ? __('Once') : __(':count times', { count: review.helpful }) }}</dd>
                        </div>
                    </dl>
                </Panel>

                <Panel :heading="__('Note')">
                    <form v-if="can.moderate" class="space-y-3 p-4" @submit.prevent="saveNote">
                        <Field :instructions="__('Only editors see this.')" :error="noteForm.errors.note">
                            <Textarea v-model="noteForm.note" :limit="2000" elastic :aria-label="__('Note')" />
                        </Field>
                        <Button type="submit" size="sm" :loading="noteForm.processing" :text="__('Save note')" />
                    </form>
                    <p v-else class="whitespace-pre-line p-4 text-sm">{{ review.note || '—' }}</p>
                </Panel>
            </div>
        </div>
    </div>
</template>
