(function () {
  const methods = ["colibrix_gateway_card", "colibrix_gateway_apm"];
  const el = window.wp.element.createElement;

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
    const iconUrls = Array.isArray(settings.icons)
      ? settings.icons.filter(Boolean)
      : settings.icon
        ? [settings.icon]
        : [];

    const Label = function () {
      const children = [
        el(
          "span",
          {
            className: "wc-block-components-payment-method-label",
          },
          labelText
        ),
      ];

      if (iconUrls.length) {
        children.push(
          el(
            "span",
            { className: "wc-colibrix-gateway-icons", key: "icons" },
            iconUrls.map(function (iconUrl, index) {
              return el("img", {
                key: "icon-" + index,
                className: "wc-colibrix-gateway-icon",
                src: iconUrl,
                alt: labelText,
              });
            })
          )
        );
      }

      return el(
        "span",
        { className: "wc-colibrix-gateway-icons-label" },
        children
      );
    };

    const Content = function () {
      return el("span", null, descriptionText);
    };

    window.wc.wcBlocksRegistry.registerPaymentMethod({
      name: methodName,
      label: el(Label, null),
      content: el(Content, null),
      edit: el(Content, null),
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
