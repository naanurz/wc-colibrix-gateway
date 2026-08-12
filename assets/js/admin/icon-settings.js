(function ($) {
  "use strict";

  var frame;
  var activeTargetId = "";
  var dragUrl = "";
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

  function setUrls($input, urls) {
    urls = urls.filter(Boolean).slice(0, maxIcons);
    $input.val(urls.join("\n")).trigger("change");
    renderPreview($input);
  }

  function clearDropMarkers($preview) {
    $preview
      .find(".wc-colibrix-gateway-icon-chip")
      .removeClass("is-drop-before is-drop-after is-dragging");
  }

  function reorderUrls(urls, fromUrl, toUrl, placeAfter) {
    var fromIndex = urls.indexOf(fromUrl);
    var toIndex = urls.indexOf(toUrl);

    if (fromIndex < 0 || toIndex < 0 || fromUrl === toUrl) {
      return urls;
    }

    urls = urls.slice();
    urls.splice(fromIndex, 1);
    toIndex = urls.indexOf(toUrl);
    var insertIndex = placeAfter ? toIndex + 1 : toIndex;
    urls.splice(insertIndex, 0, fromUrl);
    return urls;
  }

  function createChip(url, dragLabel, removeLabel) {
    var $chip = $(
      '<span class="wc-colibrix-gateway-icon-chip" role="listitem"></span>'
    );
    $chip.attr({
      "data-url": url,
      title: dragLabel,
      draggable: "true",
    });
    $chip.append(
      $("<span/>", {
        class: "wc-colibrix-gateway-icon-handle",
        "aria-hidden": "true",
        text: "⋮⋮",
      })
    );
    $chip.append(
      $("<span/>", {
        class: "wc-colibrix-gateway-icon-thumb",
        "aria-hidden": "true",
        css: { "background-image": 'url("' + String(url).replace(/"/g, '\\"') + '")' },
      })
    );
    $chip.append(
      $("<button/>", {
        type: "button",
        class: "wc-colibrix-gateway-remove-icon",
        "aria-label": removeLabel,
        text: "×",
        draggable: false,
      })
    );
    return $chip;
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

    $preview.empty();
    urls.forEach(function (url) {
      $preview.append(createChip(url, dragLabel, removeLabel));
    });

    $add.prop("disabled", urls.length >= maxIcons);
    $clear.prop("disabled", urls.length === 0);
  }

  $(document).on("dragstart", ".wc-colibrix-gateway-icon-chip", function (event) {
    if ($(event.target).closest(".wc-colibrix-gateway-remove-icon").length) {
      event.preventDefault();
      return;
    }

    var $chip = $(this);
    dragUrl = $chip.attr("data-url") || "";
    $chip.addClass("is-dragging");

    if (event.originalEvent && event.originalEvent.dataTransfer) {
      event.originalEvent.dataTransfer.effectAllowed = "move";
      event.originalEvent.dataTransfer.setData("text/plain", dragUrl);
      try {
        event.originalEvent.dataTransfer.setDragImage($chip.get(0), 16, 16);
      } catch (err) {
        // Older browsers may not support setDragImage.
      }
    }
  });

  $(document).on("dragend", ".wc-colibrix-gateway-icon-chip", function () {
    var $preview = $(this).closest(".wc-colibrix-gateway-icons-preview");
    clearDropMarkers($preview);
    dragUrl = "";
  });

  $(document).on("dragover", ".wc-colibrix-gateway-icon-chip", function (event) {
    event.preventDefault();
    event.stopPropagation();

    var $target = $(this);
    var targetUrl = $target.attr("data-url") || "";
    var $preview = $target.closest(".wc-colibrix-gateway-icons-preview");

    if (!dragUrl || !targetUrl || dragUrl === targetUrl) {
      return;
    }

    var bounds = this.getBoundingClientRect();
    var clientX =
      event.originalEvent && typeof event.originalEvent.clientX === "number"
        ? event.originalEvent.clientX
        : bounds.left;
    var placeAfter = clientX > bounds.left + bounds.width / 2;

    clearDropMarkers($preview);
    $target.addClass(placeAfter ? "is-drop-after" : "is-drop-before");
    $preview.find('[data-url="' + dragUrl.replace(/"/g, '\\"') + '"]').addClass("is-dragging");

    if (event.originalEvent && event.originalEvent.dataTransfer) {
      event.originalEvent.dataTransfer.dropEffect = "move";
    }
  });

  $(document).on("dragover", ".wc-colibrix-gateway-icons-preview", function (event) {
    event.preventDefault();
  });

  $(document).on("drop", ".wc-colibrix-gateway-icon-chip", function (event) {
    event.preventDefault();
    event.stopPropagation();

    var $target = $(this);
    var targetUrl = $target.attr("data-url") || "";
    var $preview = $target.closest(".wc-colibrix-gateway-icons-preview");
    var targetId = $preview.data("target");
    var $input = $("#" + targetId);
    var bounds = this.getBoundingClientRect();
    var clientX =
      event.originalEvent && typeof event.originalEvent.clientX === "number"
        ? event.originalEvent.clientX
        : bounds.left;
    var placeAfter = clientX > bounds.left + bounds.width / 2;
    var fromUrl =
      dragUrl ||
      (event.originalEvent &&
        event.originalEvent.dataTransfer &&
        event.originalEvent.dataTransfer.getData("text/plain")) ||
      "";

    clearDropMarkers($preview);
    dragUrl = "";

    if (!$input.length || !fromUrl || !targetUrl || fromUrl === targetUrl) {
      return;
    }

    setUrls($input, reorderUrls(getUrls($input), fromUrl, targetUrl, placeAfter));
  });

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

  $(document).on("mousedown", ".wc-colibrix-gateway-remove-icon", function (event) {
    event.stopPropagation();
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
