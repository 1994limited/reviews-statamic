<!--
    A review's status as a coloured badge.
-->
<script>
import { Badge } from '@statamic/cms/ui';

const colours = { pending: 'amber', approved: 'green', rejected: 'gray', spam: 'red' };

export default {
    components: { Badge },

    props: {
        status: { type: String, required: true },
        label: { type: String, required: true },
    },

    computed: {
        colour() {
            return colours[this.status] ?? 'default';
        },
    },
};
</script>

<template>
    <Badge :text="label" :color="colour" size="sm" />
</template>
