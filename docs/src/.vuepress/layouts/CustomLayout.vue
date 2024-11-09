<script setup>

import Theme from "vitepress/theme";
import FrameworkPromo from "./FrameworkPromo.vue";

const {Layout} = Theme;

</script>

<template>
  <Layout>
    <template #home-features-after>
      <FrameworkPromo />
    </template>
  </Layout>
</template>
