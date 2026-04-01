(function () {
  const methods = ["colibrix_gateway_card", "colibrix_gateway_apm"];

  methods.forEach(function (methodName) {
    const settings = window.wc.wcSettings.getSetting(methodName + "_data", {});
    if (!settings || Object.keys(settings).length === 0) {
      return;
    }
    const labelText = window.wp.htmlEntities.decodeEntities(
      settings.title || "Colibrix Gateway"
    );
    const descriptionText = window.wp.htmlEntities.decodeEntities(
      settings.description || ""
    );

    const Label = function () {
      return window.wp.element.createElement("span", null, labelText);
    };

    const Content = function () {
      return window.wp.element.createElement("span", null, descriptionText);
    };

    window.wc.wcBlocksRegistry.registerPaymentMethod({
      name: methodName,
      label: window.wp.element.createElement(Label, null),
      content: window.wp.element.createElement(Content, null),
      edit: window.wp.element.createElement(Content, null),
      canMakePayment: function () {
        return true;
      },
      ariaLabel: labelText,
      supports: {
        features: settings.supports || ["products"],
      },
    });
  });
})();
