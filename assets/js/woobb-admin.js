jQuery(function ($) {
  const ruleTemplate = (id, postId) => `
    <tr id="rule_row_${id}">
      <td colspan="3">
        <div class="woobb-rule-wrapper">
          <div class="woobb-rule-header">
            <span>Buy</span>
            <input type="number" class="woobb_parent_product_qty" data-product_id="${postId}" min="1" value="1">
            <span>number of current product and get</span>
            <button class="button button-primary add_free_product" type="button" data-rule_id="rule_${id}">Add free products +</button>
            <button class="button remove_parent_row" type="button" data-rule_id="rule_${id}">Remove</button>
          </div>

          <table id="rule_${id}" class="woobb_rule_table widefat striped">
            <thead>
              <tr>
                <th>Free Product</th>
                <th>Quantity</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody class="free-products-list"></tbody>
          </table>
        </div>
      </td>
    </tr>
  `;

  const freeProductTemplate = (productsList) => `
    <tr>
      <td>${productsList}</td>
      <td><input type="number" class="free-product-qty woobb-input" min="1" value="1"></td>
      <td><button type="button" class="button woobb-danger-btn">Remove</button></td>
    </tr>
  `;

  // ➕ Add new rule
  $(document).on("click", "#woobb_add_rule", function () {
    const uniqueId = Date.now();
    $("#woobb_bogo_rules > tbody").append(
      ruleTemplate(uniqueId, woobbLocal.post_id)
    );
  });

  // ➕ Add free product
  $(document).on("click", ".add_free_product", function () {
    const $table = $(`#${$(this).data("rule_id")}`).find(".free-products-list");
    $table.append(freeProductTemplate(woobbLocal.products_list));
  });

  // ❌ Remove free product
  $(document).on("click", ".woobb-danger-btn", function () {
    $(this).closest("tr").remove();
  });

  // ❌ Remove rule
  $(document).on("click", ".remove_parent_row", function () {
    $(`#rule_row_${$(this).data("rule_id").split("_")[1]}`).remove();
  });

  function serializeWoobbRules() {
    const rules = [];

    // iterate each top-level rule wrapper row
    $("#woobb_bogo_rules tbody")
      .find(".woobb-rule-wrapper")
      .each(function () {
        const $wrap = $(this);
        const productId = $wrap
          .find(".woobb_parent_product_qty")
          .data("product_id");
        const productQty =
          parseInt($wrap.find(".woobb_parent_product_qty").val(), 10) || 1;

        const free_items = [];
        $wrap.find(".free-products-list tr").each(function () {
          const $tr = $(this);
          const freeId = $tr.find("select").val() || "";
          const freeQty =
            parseInt($tr.find(".free-product-qty").val(), 10) || 1;
          if (freeId) free_items.push({ id: freeId, qty: freeQty });
        });

        // if there are free items, include the rule (you can change this behaviour)
        if (free_items.length) {
          rules.push({
            product_id: productId,
            product_qty: productQty,
            free_items: free_items,
          });
        }
      });

    return rules;
  }

  // ---------- Save handler (AJAX) ----------
  $(document).on("click", "#woobb_save_rules", function () {
    const $btn = $(this);
    const rules = serializeWoobbRules();

    // Basic UI feedback
    const $notice = $("#woobb_save_notice");
    $btn.prop("disabled", true);
    $notice.text("Saving...");

    $.ajax({
      url: woobbLocal.ajax,
      method: "POST",
      dataType: "json",
      data: {
        action: "woobb_save_rules",
        post_id: woobbLocal.post_id,
        rules: JSON.stringify(rules),
        nonce: woobbLocal.nonce,
      },
    })
      .done(function (resp) {
        if (resp && resp.success) {
          $notice.text("✅ Rules saved.");
        } else {
          $notice.text("❌ Save failed.");
          console && console.log("woobb save response:", resp);
        }
      })
      .fail(function (xhr, status, err) {
        $notice.text("❌ AJAX error.");
        console && console.error(err || status, xhr.responseText);
      })
      .always(function () {
        $btn.prop("disabled", false);
        setTimeout(function () {
          $notice.text("");
        }, 2500);
      });
  });
});
