(function ($) {
  "use strict";

  var frame;
  var activeTargetId = "";
  var maxIcons =
    (window.wcColibrixGatewayIconSettings &&
      window.wcColibrixGatewayIconSettings.maxIcons) ||
    3;

  function getUrls($input) {
    return $.trim($input.val())
      .split(/\r\n|\r|\n/)
      .map(function (url) {
        return $.trim(url);
      })
      .filter(Boolean)
      .slice(0, maxIcons);
  }

  function setUrls($input, urls) {
    urls = urls.filter(Boolean).slice(0, maxIcons);
    $input.val(urls.join("\n")).trigger("change");
    renderPreview($input);
  }

  function renderPreview($input) {
    var targetId = $input.attr("id");
    var urls = getUrls($input);
    var $preview = $(
      '.wc-colibrix-gateway-icons-preview[data-target="' + targetId + '"]'
    );
    var $add = $(
      '.wc-colibrix-gateway-upload-icons[data-target="' + targetId + '"]'
    );
    var $clear = $(
      '.wc-colibrix-gateway-clear-icons[data-target="' + targetId + '"]'
    );
    var removeLabel =
      (window.wcColibrixGatewayIconSettings &&
        window.wcColibrixGatewayIconSettings.remove) ||
      "Remove icon";

    $preview.empty();
    urls.forEach(function (url) {
      var $chip = $(
        '<span class="wc-colibrix-gateway-icon-chip" style="display:inline-flex;align-items:center;gap:4px;border:1px solid #c3c4c7;border-radius:4px;padding:4px 6px;background:#fff;"></span>'
      );
      $chip.attr("data-url", url);
      $chip.append(
        $("<img/>", {
          src: url,
          alt: "",
          css: { maxHeight: "24px" },
        })
      );
      $chip.append(
        $("<button/>", {
          type: "button",
          class: "button-link-delete wc-colibrix-gateway-remove-icon",
          "aria-label": removeLabel,
          text: "×",
        })
      );
      $preview.append($chip);
    });

    $add.prop("disabled", urls.length >= maxIcons);
    $clear.prop("disabled", urls.length === 0);
  }

  $(document).on("click", ".wc-colibrix-gateway-upload-icons", function (event) {
    event.preventDefault();

    var targetId = $(this).data("target");
    var $input = $("#" + targetId);

    if (!$input.length || typeof wp === "undefined" || !wp.media) {
      return;
    }

    activeTargetId = targetId;

    if (frame) {
      frame.open();
      return;
    }

    frame = wp.media({
      title:
        (window.wcColibrixGatewayIconSettings &&
          window.wcColibrixGatewayIconSettings.title) ||
        "Select payment method icons",
      button: {
        text:
          (window.wcColibrixGatewayIconSettings &&
            window.wcColibrixGatewayIconSettings.button) ||
          "Use selected images",
      },
      multiple: true,
      library: {
        type: "image",
      },
    });

    frame.on("select", function () {
      var $target = $("#" + activeTargetId);
      if (!$target.length) {
        return;
      }

      var current = getUrls($target);
      var selected = frame.state().get("selection").toJSON() || [];
      selected.forEach(function (attachment) {
        if (
          attachment &&
          attachment.url &&
          current.indexOf(attachment.url) === -1 &&
          current.length < maxIcons
        ) {
          current.push(attachment.url);
        }
      });
      setUrls($target, current);
    });

    frame.open();
  });

  $(document).on("click", ".wc-colibrix-gateway-remove-icon", function (event) {
    event.preventDefault();

    var $chip = $(this).closest(".wc-colibrix-gateway-icon-chip");
    var $preview = $chip.closest(".wc-colibrix-gateway-icons-preview");
    var targetId = $preview.data("target");
    var $input = $("#" + targetId);
    var removeUrl = $chip.data("url");

    if (!$input.length) {
      return;
    }

    setUrls(
      $input,
      getUrls($input).filter(function (url) {
        return url !== removeUrl;
      })
    );
  });

  $(document).on("click", ".wc-colibrix-gateway-clear-icons", function (event) {
    event.preventDefault();

    var targetId = $(this).data("target");
    var $input = $("#" + targetId);
    if (!$input.length) {
      return;
    }

    setUrls($input, []);
  });
})(jQuery);
