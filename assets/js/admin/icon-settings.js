(function ($) {
  "use strict";

  var frame;
  var activeTargetId = "";

  function updatePreview($input) {
    var url = $.trim($input.val());
    var targetId = $input.attr("id");
    var $preview = $(
      '.wc-colibrix-gateway-icon-preview[data-preview-for="' + targetId + '"]'
    );
    var $clear = $(
      '.wc-colibrix-gateway-clear-icon[data-target="' + targetId + '"]'
    );

    if (url) {
      $preview.attr("src", url).show();
      $clear.prop("disabled", false);
    } else {
      $preview.attr("src", "").hide();
      $clear.prop("disabled", true);
    }
  }

  $(document).on("click", ".wc-colibrix-gateway-upload-icon", function (event) {
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
        "Select payment method icon",
      button: {
        text:
          (window.wcColibrixGatewayIconSettings &&
            window.wcColibrixGatewayIconSettings.button) ||
          "Use this image",
      },
      multiple: false,
      library: {
        type: "image",
      },
    });

    frame.on("select", function () {
      var $target = $("#" + activeTargetId);
      var attachment = frame.state().get("selection").first().toJSON();
      if (!$target.length || !attachment || !attachment.url) {
        return;
      }

      $target.val(attachment.url).trigger("change");
      updatePreview($target);
    });

    frame.open();
  });

  $(document).on("click", ".wc-colibrix-gateway-clear-icon", function (event) {
    event.preventDefault();

    var targetId = $(this).data("target");
    var $input = $("#" + targetId);
    if (!$input.length) {
      return;
    }

    $input.val("").trigger("change");
    updatePreview($input);
  });

  $(document).on(
    "input change",
    'input[name^="woocommerce_"][name$="_icon"]',
    function () {
      updatePreview($(this));
    }
  );
})(jQuery);
