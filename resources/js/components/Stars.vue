<!--
    A rating as five stars, for reading (not choosing).
-->
<script>
export default {
    props: {
        rating: { type: Number, default: 0 },
        size: { type: String, default: 'sm' },
    },

    computed: {
        stars() {
            return [1, 2, 3, 4, 5].map((n) => (this.rating - (n - 1) >= 0.75 ? 'full' : this.rating - (n - 1) >= 0.25 ? 'half' : 'empty'));
        },
    },
};
</script>

<template>
    <span class="inline-flex shrink-0 items-center gap-px" :class="size === 'lg' ? 'text-xl' : 'text-sm'" role="img" :aria-label="__(':rating out of 5', { rating })">
        <span
            v-for="(star, index) in stars"
            :key="index"
            aria-hidden="true"
            :class="star === 'empty' ? 'text-gray-300 dark:text-gray-600!' : 'text-amber-500'"
        >{{ star === 'half' ? '⯪' : '★' }}</span>
    </span>
</template>
