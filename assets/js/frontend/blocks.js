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
      ? settings.icons.filter(Boolean).slice(0, 3)
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

      iconUrls.forEach(function (iconUrl, index) {
        children.push(
          el("img", {
            key: "icon-" + index,
            src: iconUrl,
            alt: labelText,
            style: {
              maxHeight: "24px",
              marginLeft: index === 0 ? "8px" : "4px",
              verticalAlign: "middle",
            },
          })
        );
      });

      return el(
        "span",
        { style: { display: "inline-flex", alignItems: "center" } },
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
