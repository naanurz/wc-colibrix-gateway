(function ($) {
  "use strict";

  var frame;
  var activeTargetId = "";
  var maxIcons =
    (window.wcColibrixGatewayIconSettings &&
      window.wcColibrixGatewayIconSettings.maxIcons) ||
    10;

  function getUrls($input) {
    return $.trim($input.val())
      .split(/\r\n|\r|\n/)
      .map(function (url) {
        return $.trim(url);
      })
      .filter(Boolean)
      .slice(0, maxIcons);
  }

  function setUrls($input, urls, options) {
    options = options || {};
    urls = urls.filter(Boolean).slice(0, maxIcons);
    $input.val(urls.join("\n")).trigger("change");
    if (!options.skipRender) {
      renderPreview($input);
    }
  }

  function syncOrderFromPreview($preview) {
    var targetId = $preview.data("target");
    var $input = $("#" + targetId);
    if (!$input.length) {
      return;
    }

    var urls = [];
    $preview.children(".wc-colibrix-gateway-icon-chip").each(function () {
      var url = $(this).attr("data-url");
      if (url) {
        urls.push(url);
      }
    });
    setUrls($input, urls, { skipRender: true });
  }

  function initSortable($preview) {
    if (!$preview.length || typeof $.fn.sortable !== "function") {
      return;
    }

    if ($preview.hasClass("ui-sortable")) {
      $preview.sortable("destroy");
    }

    $preview.sortable({
      items: "> .wc-colibrix-gateway-icon-chip",
      cursor: "move",
      tolerance: "pointer",
      placeholder: "wc-colibrix-gateway-icon-chip-placeholder",
      forcePlaceholderSize: true,
      opacity: 0.9,
      cancel: "button, a, input, textarea",
      start: function (event, ui) {
        ui.placeholder.width(Math.max(ui.item.outerWidth(), 96));
        ui.placeholder.height(Math.max(ui.item.outerHeight(), 36));
        ui.item.css("width", ui.item.outerWidth());
      },
      stop: function (event, ui) {
        ui.item.css("width", "");
      },
      update: function () {
        syncOrderFromPreview($preview);
      },
    });
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
    var dragLabel =
      (window.wcColibrixGatewayIconSettings &&
        window.wcColibrixGatewayIconSettings.drag) ||
      "Drag to reorder";

    if ($preview.hasClass("ui-sortable")) {
      $preview.sortable("destroy");
    }

    $preview.empty();
    urls.forEach(function (url) {
      var $chip = $('<span class="wc-colibrix-gateway-icon-chip"></span>');
      $chip.attr({
        "data-url": url,
        title: dragLabel,
      });
      $chip.append(
        $("<span/>", {
          class: "wc-colibrix-gateway-icon-handle",
          "aria-hidden": "true",
          text: "⋮⋮",
        })
      );
      $chip.append(
        $("<img/>", {
          src: url,
          alt: "",
          draggable: false,
        })
      );
      $chip.append(
        $("<button/>", {
          type: "button",
          class: "wc-colibrix-gateway-remove-icon",
          "aria-label": removeLabel,
          text: "×",
        })
      );
      $preview.append($chip);
    });

    $add.prop("disabled", urls.length >= maxIcons);
    $clear.prop("disabled", urls.length === 0);
    initSortable($preview);
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
    event.stopPropagation();

    var $chip = $(this).closest(".wc-colibrix-gateway-icon-chip");
    var $preview = $chip.closest(".wc-colibrix-gateway-icons-preview");
    var targetId = $preview.data("target");
    var $input = $("#" + targetId);
    var removeUrl = $chip.attr("data-url");

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

  $(function () {
    $(".wc-colibrix-gateway-icons-input").each(function () {
      renderPreview($(this));
    });
  });
})(jQuery);
