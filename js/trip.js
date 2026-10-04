$(function () {
  /* =========================================================
     CONFIG
     ========================================================= */
  var ITEM_ROWS_WRAPPER = "#itemsWrap";
  var ITEM_ROW_CLASS = ".item-row";
  var ADD_ITEM_BTN = "#addItemBtn";
  var GRAND_TOTAL_ID = "#itemsGrandTotal";

  var MODAL_ID = "#masterModal";
  var MODAL_IFRAME_ID = "#masterModalIframe";
  var MODAL_TITLE_ID = "#masterModalTitle";

  var lastSlot = "";

  var INVENTORY_MASTER =
    typeof MASTER_INVENTORY !== "undefined" ? MASTER_INVENTORY : [];
  var INITIAL_ITEMS =
    typeof TRIP_INITIAL_ITEMS !== "undefined" ? TRIP_INITIAL_ITEMS : [];
  var TRUCKS_MASTER = typeof MASTER_TRUCKS !== "undefined" ? MASTER_TRUCKS : [];
  var DRIVERS_MASTER =
    typeof MASTER_DRIVERS !== "undefined" ? MASTER_DRIVERS : [];

  var INVENTORY_BY_ID = {};
  for (var i = 0; i < INVENTORY_MASTER.length; i++) {
    INVENTORY_BY_ID[INVENTORY_MASTER[i].id] = INVENTORY_MASTER[i];
  }

  var TRUCK_BY_ID = {};
  for (var j = 0; j < TRUCKS_MASTER.length; j++) {
    TRUCK_BY_ID[TRUCKS_MASTER[j].id] = TRUCKS_MASTER[j];
  }

  var DRIVER_BY_ID = {};
  for (var k = 0; k < DRIVERS_MASTER.length; k++) {
    DRIVER_BY_ID[DRIVERS_MASTER[k].id] = DRIVERS_MASTER[k];
  }

  /* =========================================================
     HELPERS
     ========================================================= */
  function formatMoney(n) {
    n = parseFloat(n) || 0;
    return n.toLocaleString("en-IN", {
      maximumFractionDigits: 2,
      minimumFractionDigits: 0,
    });
  }

  function num(v) {
    var n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }

  function toInt(v) {
    var n = parseInt(v, 10);
    return isNaN(n) ? 0 : n;
  }

  function escHtml(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function buildDriverLabel(driver) {
    if (!driver) return "";
    var lbl = driver.name || "";
    if (driver.phone) lbl += " — " + driver.phone;
    return lbl;
  }

  function buildTruckLabel(truck) {
    if (!truck) return "";
    var lbl = truck.number || "";
    if (truck.type) lbl += " — " + truck.type;
    return lbl;
  }

  function buildInventoryOptions() {
    var html = "";
    for (var i = 0; i < INVENTORY_MASTER.length; i++) {
      var inv = INVENTORY_MASTER[i];
      html +=
        '<option data-id="' +
        inv.id +
        '" ' +
        'data-unit="' +
        escHtml(inv.unit) +
        '" ' +
        'value="' +
        escHtml(inv.name) +
        '"></option>';
    }
    return html;
  }

  function showTripFlash(type, msg) {
    var $wrap = $("#tripFlash");
    if (!$wrap.length) return;

    var icon =
      type === "warning"
        ? '<i class="fas fa-exclamation-triangle mr-1"></i>'
        : type === "danger"
          ? '<i class="fas fa-exclamation-circle mr-1"></i>'
          : '<i class="fas fa-check-circle mr-1"></i>';

    var html =
      '<div class="alert alert-' +
      type +
      ' alert-dismissible fade show" role="alert">' +
      icon +
      escHtml(msg) +
      '<button type="button" class="close" data-dismiss="alert">' +
      "<span>&times;</span></button></div>";

    $wrap.html(html);
    setTimeout(function () {
      $wrap.find(".alert").fadeOut(400, function () {
        $(this).remove();
      });
    }, 8000);
  }

  /* =========================================================
     RATE FETCHING VIA AJAX
     ========================================================= */
  function fetchRateForRow($row, invId, forcePartyId) {
    if (currentFreightMode() !== 1 || !invId) return;

    var pId =
      forcePartyId !== undefined ? forcePartyId : $("#party_id").val() || 0;

    $.ajax({
      url: "trip.php?action=get_item_rate",
      type: "POST",
      dataType: "json",
      data: {
        party_id: pId,
        inventory_id: invId,
      },
      success: function (res) {
        if (res && res.success) {
          // Fill input and visually calculate the total
          var rInput = $row.find(".item-rate-input");
          rInput.val(res.rate);
          rInput.trigger("input");
        }
      },
    });
  }

  function refreshAllItemRates() {
    if (currentFreightMode() !== 1) return;
    var pId = $("#party_id").val() || 0;

    $(ITEM_ROWS_WRAPPER)
      .find(ITEM_ROW_CLASS)
      .each(function () {
        var $row = $(this);
        var invId = $row.find(".item-id-input").val();
        if (invId) {
          fetchRateForRow($row, invId, pId);
        }
      });
  }

  /* =========================================================
     FREIGHT MODE
     ========================================================= */
  function currentFreightMode() {
    var v = $(".js-freight-mode:checked").val();
    return String(v) === "1" ? 1 : 0;
  }

  function syncFreightFromItems() {
    if (currentFreightMode() !== 1) return;

    var sum = 0;
    $(ITEM_ROWS_WRAPPER)
      .find(ITEM_ROW_CLASS)
      .each(function () {
        var $row = $(this);
        var qty = toInt($row.find(".item-qty-input").val());
        var rate = num($row.find(".item-rate-input").val());
        sum += qty * rate;
      });

    $("#freight_amount").val(sum.toFixed(2));
  }

  function applyFreightModeUI() {
    var mode = currentFreightMode();

    $(".js-fm-fixed").toggleClass("checked", mode === 0);
    $(".js-fm-itemwise").toggleClass("checked", mode === 1);

    var $rateCols = $(".rate-col");
    var $amountCols = $(".amount-col");

    if (mode === 1) {
      $rateCols.removeClass("hidden");
      $amountCols.removeClass("hidden");
      $("#freight_amount").prop("readonly", true);
      syncFreightFromItems();
    } else {
      $rateCols.addClass("hidden");
      $amountCols.addClass("hidden");
      $("#freight_amount").prop("readonly", false);
    }
  }

  $(document).on("change", ".js-freight-mode", function () {
    applyFreightModeUI();
    // If they just switched to item-wise mode, we should fetch rates
    // for rows that are currently at 0 rate.
    if (currentFreightMode() === 1) {
      var pId = $("#party_id").val() || 0;
      $(ITEM_ROWS_WRAPPER)
        .find(ITEM_ROW_CLASS)
        .each(function () {
          var $row = $(this);
          var currentRate = num($row.find(".item-rate-input").val());
          var invId = $row.find(".item-id-input").val();
          // Don't overwrite if they already manually typed a rate
          if (currentRate === 0 && invId) {
            fetchRateForRow($row, invId, pId);
          }
        });
    }
  });

  $(document).on("click", ".js-fm-fixed, .js-fm-itemwise", function () {
    setTimeout(function () {
      applyFreightModeUI();
    }, 0);
  });

  /* =========================================================
     TRUCK ↔ DRIVER AUTO-FILL + HIRE CHARGE TOGGLE
     ========================================================= */
  function applyTruckDependentsUI(truck) {
    var $thc = $(".js-thc-row");

    if (truck && truck.ownership === 1) {
      $thc.addClass("show");
    } else {
      $thc.removeClass("show");
      $("#truck_hire_charge").val("");
    }

    if (truck && truck.preferred_driver_id > 0) {
      var $driverInput = $("#driver_input");
      var $driverHidden = $("#driver_id");
      var currentDriver = ($driverInput.val() || "").trim();

      if (currentDriver === "") {
        var driver = DRIVER_BY_ID[truck.preferred_driver_id];
        if (driver) {
          $driverInput.val(buildDriverLabel(driver));
          $driverHidden.val(driver.id);
        }
      }
    }
  }

  function syncTruckDependents() {
    var truckId = $("#lorry_id").val();

    if (!truckId) {
      applyTruckDependentsUI(null);
      return;
    }

    var truck = TRUCK_BY_ID[truckId];

    if (truck) {
      // Truck exists in pre-loaded dictionary
      applyTruckDependentsUI(truck);
    } else {
      // Truck is new; fetch instantly via AJAX
      $.ajax({
        url: "trip.php?action=get_lorry_details",
        type: "POST",
        dataType: "json",
        data: { lorry_id: truckId },
        success: function (res) {
          if (res && res.success) {
            var newTruck = {
              id: truckId,
              ownership: res.ownership_type,
              preferred_driver_id: res.preferred_driver_id,
            };
            // Cache it so subsequent selects don't trigger another network request
            TRUCK_BY_ID[truckId] = newTruck;
            applyTruckDependentsUI(newTruck);
          }
        },
      });
    }
  }

  function syncDriverDependents() {
    var driverId = $("#driver_id").val();
    var driver = DRIVER_BY_ID[driverId];

    if (!driver || !driver.preferred_lorry_id) return;

    var $lorryInput = $("#lorry_input");
    var $lorryHidden = $("#lorry_id");
    var currentTruck = ($lorryInput.val() || "").trim();

    if (currentTruck !== "") return;

    var truck = TRUCK_BY_ID[driver.preferred_lorry_id];
    if (!truck) return;

    $lorryInput.val(buildTruckLabel(truck));
    $lorryHidden.val(truck.id);
    syncTruckDependents();
  }

  /* =========================================================
     DATALIST ↔ HIDDEN ID SYNC
     ========================================================= */
  function syncDatalistInput($input) {
    var targetId = $input.data("target");
    if (!targetId) return;

    var listId = $input.attr("list");
    if (!listId) return;

    var typed = ($input.val() || "").trim();
    var $hidden = $("#" + targetId);

    if (typed === "") {
      $hidden.val("");
      if (targetId === "lorry_id") syncTruckDependents();
      if (targetId === "driver_id") syncDriverDependents();
      // If they clear the party, we might want to refresh rates
      if (targetId === "party_id") refreshAllItemRates();
      return;
    }

    var matchedValue = null;
    $("#" + listId)
      .find("option")
      .each(function () {
        var $opt = $(this);
        var optVal = ($opt.attr("value") || $opt.val() || "").trim();
        if (optVal === typed) {
          matchedValue = $opt.data("id");
          return false;
        }
      });

    var finalId =
      matchedValue !== null && matchedValue !== undefined ? matchedValue : "";

    // Check if ID changed
    var oldId = $hidden.val();
    $hidden.val(finalId);

    if (targetId === "lorry_id") syncTruckDependents();
    if (targetId === "driver_id") syncDriverDependents();

    // If the Party changed, refresh special rates for all selected items
    if (targetId === "party_id" && oldId !== String(finalId)) {
      refreshAllItemRates();
    }
  }

  $(document).on("input change", ".js-datalist-input", function () {
    syncDatalistInput($(this));
  });

  $(document).on("input change", ".js-datalist-input", function () {
    var $input = $(this);
    var targetId = $input.data("target");
    var $hidden = $("#" + targetId);
    if ($hidden.val() && $hidden.val() !== "0") {
      $input.removeClass("is-invalid");
    }
  });

  /* =========================================================
     ITEM ROWS
     ========================================================= */
  function buildItemRowHTML(index, prefill) {
    prefill = prefill || {};

    var datalistId = "invList_" + index;
    var optionsHtml = buildInventoryOptions();

    var itemName = prefill.item_name || "";
    var itemId = prefill.item_id || "";
    var unitVal = prefill.unit || "";
    var qtyVal =
      prefill.quantity !== undefined &&
      prefill.quantity !== null &&
      prefill.quantity !== ""
        ? prefill.quantity
        : "";
    var rateVal =
      prefill.rate !== undefined && prefill.rate !== null && prefill.rate !== ""
        ? prefill.rate
        : "";

    var qtyInt = qtyVal === "" ? "" : parseInt(qtyVal, 10);
    var rateNum = rateVal === "" ? "" : parseFloat(rateVal);

    var amount = 0;
    if (qtyInt !== "" && rateNum !== "" && !isNaN(qtyInt) && !isNaN(rateNum)) {
      amount = qtyInt * rateNum;
    }

    var amountDisplay = amount > 0 || qtyInt !== "" ? formatMoney(amount) : "0";
    var amountHidden = amount > 0 ? amount.toFixed(2) : "0";

    return (
      "" +
      '<tr class="item-row">' +
      '<td class="align-middle" data-label="Item">' +
      '<input type="text" class="form-control form-control-sm item-name-input" ' +
      'name="items[' +
      index +
      '][item_name]" ' +
      'placeholder="Type or pick inventory..." ' +
      'list="' +
      datalistId +
      '" autocomplete="off" maxlength="100" ' +
      'value="' +
      escHtml(itemName) +
      '" required>' +
      '<datalist id="' +
      datalistId +
      '">' +
      optionsHtml +
      "</datalist>" +
      '<input type="hidden" class="item-id-input" ' +
      'name="items[' +
      index +
      '][item_id]" value="' +
      escHtml(itemId) +
      '">' +
      "</td>" +
      '<td class="align-middle" data-label="Unit">' +
      '<input type="text" class="form-control form-control-sm item-unit-input" ' +
      'name="items[' +
      index +
      '][unit]" ' +
      'placeholder="Unit" maxlength="20" value="' +
      escHtml(unitVal) +
      '">' +
      "</td>" +
      '<td class="align-middle" data-label="Qty">' +
      '<input type="number" class="form-control form-control-sm item-qty-input text-right" ' +
      'name="items[' +
      index +
      '][quantity]" ' +
      'placeholder="0" step="1" min="0" value="' +
      escHtml(qtyVal) +
      '">' +
      "</td>" +
      '<td class="align-middle rate-col" data-label="Rate">' +
      '<input type="number" class="form-control form-control-sm item-rate-input text-right" ' +
      'name="items[' +
      index +
      '][rate]" ' +
      'placeholder="0.00" step="0.01" min="0" value="' +
      escHtml(rateVal) +
      '">' +
      "</td>" +
      '<td class="align-middle text-right amount-col" data-label="Amount">' +
      '<span class="item-amount-display font-weight-bold">' +
      amountDisplay +
      "</span>" +
      '<input type="hidden" class="item-amount-input" ' +
      'name="items[' +
      index +
      '][amount]" value="' +
      amountHidden +
      '">' +
      "</td>" +
      '<td class="align-middle text-center" data-label="">' +
      '<button type="button" class="btn btn-sm btn-outline-danger js-remove-item">' +
      '<i class="fas fa-times"></i>' +
      "</button>" +
      "</td>" +
      "</tr>"
    );
  }

  function reindexItemRows() {
    var optionsHtml = buildInventoryOptions();

    $(ITEM_ROWS_WRAPPER)
      .find(ITEM_ROW_CLASS)
      .each(function (i) {
        var $row = $(this);
        var newListId = "invList_" + i;
        var $input = $row.find(".item-name-input");

        $row.find("datalist").remove();
        $input.attr("list", newListId);
        $input.after(
          '<datalist id="' + newListId + '">' + optionsHtml + "</datalist>",
        );

        $row.find("input").each(function () {
          var name = $(this).attr("name");
          if (!name) return;
          $(this).attr(
            "name",
            name.replace(/items\[\d+\]/, "items[" + i + "]"),
          );
        });
      });
  }

  function addItemRow(prefill) {
    var index = $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).length;
    $(ITEM_ROWS_WRAPPER).append(buildItemRowHTML(index, prefill));

    if (!prefill || !prefill.item_name) {
      $(ITEM_ROWS_WRAPPER)
        .find(ITEM_ROW_CLASS)
        .last()
        .find(".item-name-input")
        .focus();
    }

    syncFreightFromItems();
  }

  $(document).on("click", ADD_ITEM_BTN, function (e) {
    e.preventDefault();
    addItemRow();
    applyFreightModeUI();
  });

  $(document).on("click", ".js-remove-item", function (e) {
    e.preventDefault();
    var $rows = $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS);

    if ($rows.length === 1) {
      var $row = $rows.first();
      $row.find('input[type="text"], input[type="number"]').val("");
      $row.find(".item-amount-display").text("0");
      $row.find(".item-id-input").val("");
      recalcTotals();
      return;
    }

    $(this).closest(ITEM_ROW_CLASS).remove();
    reindexItemRows();
    recalcTotals();
    applyFreightModeUI();
  });

  $(document).on("input", ".item-name-input", function () {
    var $input = $(this);
    var $row = $input.closest(ITEM_ROW_CLASS);
    var typed = ($input.val() || "").trim();

    var listId = $input.attr("list");
    var $datalist = $("#" + listId);

    var matchedId = "";
    var matchedUnit = "";

    $datalist.find("option").each(function () {
      var $opt = $(this);
      if ($opt.attr("value") === typed) {
        matchedId = $opt.data("id") || "";
        matchedUnit = $opt.data("unit") || "";
        return false;
      }
    });

    if (matchedId !== "") {
      $row.find(".item-id-input").val(matchedId);
      $row.find(".item-unit-input").val(matchedUnit || "");

      // Auto fetch the rate for this item (if item-wise mode is active)
      fetchRateForRow($row, matchedId);
    } else {
      $row.find(".item-id-input").val("");
      $row.find(".item-unit-input").val("");
    }
  });

  function recalcRow($row) {
    var qty = toInt($row.find(".item-qty-input").val());
    var rate = num($row.find(".item-rate-input").val());
    var amt = qty * rate;
    $row.find(".item-amount-display").text(formatMoney(amt));
    $row.find(".item-amount-input").val(amt.toFixed(2));
  }

  function recalcTotals() {
    var grand = 0;
    $(ITEM_ROWS_WRAPPER)
      .find(ITEM_ROW_CLASS)
      .each(function () {
        recalcRow($(this));
        grand += num($(this).find(".item-amount-input").val());
      });
    $(GRAND_TOTAL_ID).text(formatMoney(grand));
    syncFreightFromItems();
  }

  $(document).on("input", ".item-qty-input, .item-rate-input", function () {
    recalcRow($(this).closest(ITEM_ROW_CLASS));
    recalcTotals();
  });

  /* =========================================================
     KEYBOARD FLOW — Enter / Space on Add Row adds a row
     ========================================================= */
  $(document).on("keydown", ADD_ITEM_BTN, function (e) {
    if (e.which === 13 || e.which === 32) {
      e.preventDefault();
      e.stopPropagation();
      addItemRow();
      applyFreightModeUI();
      return false;
    }
  });

  /* =========================================================
     MASTER MODAL
     ========================================================= */
  function openMasterModal(entity, titleText, slot) {
    var url = entity + ".php?popup=1";
    lastSlot = slot || "";

    if (document.activeElement && document.activeElement.blur) {
      document.activeElement.blur();
    }

    $(MODAL_IFRAME_ID).attr("src", url);
    $(MODAL_TITLE_ID).text(titleText || "Add New " + entity);
    $(MODAL_ID).data("entity", entity);
    $(MODAL_ID).data("slot", slot || "");
    $(MODAL_ID).modal({ backdrop: "static", keyboard: false, show: true });
  }

  function closeMasterModal() {
    if (document.activeElement && document.activeElement.blur) {
      document.activeElement.blur();
    }
    $(MODAL_ID).modal("hide");
    $(MODAL_ID).one("hidden.bs.modal.masterOnce", function () {
      $(MODAL_IFRAME_ID).attr("src", "about:blank");
    });
  }

  $(document).on("click", ".js-open-master", function (e) {
    e.preventDefault();
    e.stopPropagation();

    var entity = $(this).data("entity");
    var slot = $(this).data("slot") || "";
    var title = $(this).data("title") || "Add New " + entity;
    if (!entity) return;
    openMasterModal(entity, title, slot);
  });

  window.addEventListener("message", function (e) {
    var data = e.data;
    if (!data || typeof data !== "object") return;

    if (data.type === "master-saved") {
      var entity = data.entity;
      var id = data.id;
      var label = data.label || "";
      var slot = lastSlot;

      if (entity === "party") {
        if (slot === "consignee") {
          setDatalistValue("party", "consignee", id, label);
          showTripFlash("success", "New consignee added and selected.");
        } else {
          setDatalistValue("party", "consignor", id, label);
          showTripFlash("success", "New consignor added and selected.");
        }
      } else if (entity === "lorry") {
        setDatalistValue("lorry", "lorry", id, label);
        showTripFlash("success", "New truck added and selected.");
      } else if (entity === "driver") {
        setDatalistValue("driver", "driver", id, label);
        showTripFlash("success", "New driver added and selected.");
      }

      closeMasterModal();
      return;
    }

    if (data.type === "master-cancelled") {
      closeMasterModal();
      return;
    }
  });

  function setDatalistValue(entity, slot, id, label) {
    var map = {
      lorry: { input: "#lorry_input", hidden: "#lorry_id", list: "#lorryList" },
      driver: {
        input: "#driver_input",
        hidden: "#driver_id",
        list: "#driverList",
      },
      consignor: {
        input: "#party_input",
        hidden: "#party_id",
        list: "#partyList",
      },
      consignee: {
        input: "#consignee_party_input",
        hidden: "#consignee_party_id",
        list: "#consigneeList",
      },
    };
    if (slot === "" && entity === "party") slot = "consignor";

    var cfg = map[slot];
    if (!cfg) return;

    var $list = $(cfg.list);
    var exists = false;
    $list.find("option").each(function () {
      if ($(this).attr("value") === label) {
        exists = true;
        return false;
      }
    });
    if (!exists) {
      $list.append(
        '<option data-id="' + id + '" value="' + escHtml(label) + '"></option>',
      );
    }

    // Save previous so we can check if it actually changed
    var oldId = $(cfg.hidden).val();

    $(cfg.input).val(label);
    $(cfg.hidden).val(id);

    if (slot === "lorry") syncTruckDependents();
    if (slot === "driver") syncDriverDependents();

    // Auto-refresh rates for items if Consignor changes
    if (slot === "consignor" && oldId !== String(id)) {
      refreshAllItemRates();
    }
  }

  /* =========================================================
     TRIP NO — edit modal + duplicate check
     ========================================================= */
  (function initTripNoEditor() {
    var $hidden = $("#trip_no");
    var $badgeText = $("#tripNoText");
    var $editBtn = $("#tripNoEditBtn");
    var $modal = $("#tripNoModal");
    var $input = $("#trip_no_input");
    var $status = $("#tripno_status");
    var $modalSave = $("#tripNoModalSave");

    if (!$hidden.length || !$modal.length) return;

    var editId = parseInt($hidden.data("edit-id"), 10) || 0;
    var lastChecked = "";
    var isDuplicate = false;
    var pendingAjax = null;
    var typeTimer = null;

    function setStatus(state, msg) {
      $status.removeClass("checking ok error");

      if (state === "checking") {
        $status
          .addClass("checking")
          .html('<i class="fas fa-spinner fa-spin mr-1"></i>' + msg);
      } else if (state === "ok") {
        $status
          .addClass("ok")
          .html('<i class="fas fa-check-circle mr-1"></i>' + msg);
      } else if (state === "error") {
        $status
          .addClass("error")
          .html('<i class="fas fa-exclamation-circle mr-1"></i>' + msg);
      } else {
        $status.html(
          '<i class="fas fa-info-circle mr-1"></i>Auto-generated. You may edit if needed.',
        );
      }
    }

    function applyDuplicate(isDup) {
      isDuplicate = !!isDup;

      if (isDuplicate) {
        $input.addClass("is-duplicate");
        $modalSave.prop("disabled", true);
      } else {
        $input.removeClass("is-duplicate");
        $modalSave.prop("disabled", false);
      }
    }

    function checkTripNo() {
      var val = $.trim($input.val()).toUpperCase();

      if ($input.val() !== val) {
        $input.val(val);
      }

      if (val === "") {
        setStatus("error", "Trip No is required.");
        applyDuplicate(true);
        lastChecked = "";
        return;
      }

      if (val === lastChecked) return;
      lastChecked = val;

      setStatus("checking", "Checking...");

      if (pendingAjax) pendingAjax.abort();

      pendingAjax = $.ajax({
        url: "ajax/check-trip-no.php",
        type: "GET",
        dataType: "json",
        data: { trip_no: val, exclude_id: editId },
      })
        .done(function (res) {
          pendingAjax = null;

          if (!res || !res.success) {
            setStatus("", "");
            applyDuplicate(false);
            return;
          }

          if (res.exists) {
            setStatus(
              "error",
              'Trip No "' + val + '" already exists. Please choose another.',
            );
            applyDuplicate(true);
          } else {
            setStatus("ok", "Trip No is available.");
            applyDuplicate(false);
          }
        })
        .fail(function (xhr, status) {
          if (status === "abort") return;
          pendingAjax = null;
          setStatus("", "Could not verify. Please try again.");
          applyDuplicate(false);
        });
    }

    $editBtn.on("click", function (e) {
      e.preventDefault();

      var current = $.trim($hidden.val());
      $input.val(current);
      lastChecked = "";
      isDuplicate = false;
      $input.removeClass("is-duplicate");
      $modalSave.prop("disabled", false);

      if (current === "") {
        setStatus("error", "Trip No is required.");
      } else {
        setStatus("", "Auto-generated. You may edit if needed.");
      }

      $modal.modal({ backdrop: "static", keyboard: false, show: true });

      setTimeout(function () {
        $input.trigger("focus").trigger("select");
        if (current !== "") {
          checkTripNo();
        }
      }, 300);
    });

    $input.on("input", function () {
      var pos = this.selectionStart;
      this.value = this.value.toUpperCase();
      this.setSelectionRange(pos, pos);
    });

    $input.on("input", function () {
      clearTimeout(typeTimer);
      typeTimer = setTimeout(checkTripNo, 400);
    });

    $input.on("blur", checkTripNo);

    $modalSave.on("click", function () {
      if (isDuplicate) {
        setStatus("error", "Fix the duplicate Trip No before saving.");
        return;
      }

      var val = $.trim($input.val()).toUpperCase();

      if (val === "") {
        setStatus("error", "Trip No is required.");
        return;
      }

      $hidden.val(val);
      $badgeText.text(val);
      $modal.modal("hide");
    });

    $input.on("keydown", function (e) {
      if (e.which === 13) {
        e.preventDefault();
        $modalSave.trigger("click");
      }
    });
  })();

  /* =========================================================
     BOOTSTRAP — render one blank row on Add form.
     ========================================================= */
  (function bootstrapInitialRows() {
    var seeded = 0;

    if (INITIAL_ITEMS && INITIAL_ITEMS.length > 0) {
      for (var r = 0; r < INITIAL_ITEMS.length; r++) {
        addItemRow(INITIAL_ITEMS[r]);
        seeded++;
      }
    }

    var liveRows = $(ITEM_ROWS_WRAPPER).find(ITEM_ROW_CLASS).length;
    if (liveRows === 0) {
      addItemRow();
      seeded++;
    }

    applyFreightModeUI();
    recalcTotals();

    var onlyRowBlank =
      liveRows <= 1 &&
      ($(ITEM_ROWS_WRAPPER)
        .find(ITEM_ROW_CLASS)
        .first()
        .find(".item-name-input")
        .val() || "") === "";

    if (onlyRowBlank) {
      $(ITEM_ROWS_WRAPPER)
        .find(ITEM_ROW_CLASS)
        .first()
        .find(".item-name-input")
        .focus();
    }
  })();

  window.tripJS = {
    addItemRow: addItemRow,
    recalcTotals: recalcTotals,
    openMasterModal: openMasterModal,
    setDatalistValue: setDatalistValue,
  };
});
