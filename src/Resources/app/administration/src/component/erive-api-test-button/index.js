const { Component, Mixin } = Shopware;
import template from "./erive-api-test-button.html.twig";
// console(template);

Component.register("erive-api-test-button", {
  template,
  props: ["label"],
  inject: ["eriveApiTest"],
  mixins: [Mixin.getByName("notification")],
  data() {
    return {
      isLoading: false,
      isSaveSuccessful: false,
    };
  },

  computed: {
    pluginConfig() {
      let $parent = this.$parent;
      while ($parent.actualConfigData === undefined) {
        $parent = $parent.$parent;
      }
      return $parent.actualConfigData.null;
    },
  },

  methods: {
    saveFinish() {
      this.isSaveSuccessful = false;
    },

    check() {
      this.isLoading = true;
      this.eriveApiTest
        .check(this.pluginConfig)
        .then((res) => {
          if (res.status === 200 || res.statusText === "OK" || res.success) {
            this.isSaveSuccessful = true;
            this.createNotificationSuccess({
              title: this.$tc("erive-api-test-button.title"),
              message: this.$tc("erive-api-test-button.success"),
            });
          } else {
            this.createNotificationError({
              title: this.$tc("erive-api-test-button.title"),
              message: this.$tc("erive-api-test-button.error"),
            });
          }
        })
        .catch((err) => {
          this.createNotificationError({
            title: this.$tc("erive-api-test-button.title"),
            message: this.$tc("erive-api-test-button.error"),
          });
        })
        .finally(() => {
          this.isLoading = false;
        });
    },
  },
});
