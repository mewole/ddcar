/**
 * DDCar Sync Assistant - Content Script for TikTok Shop Seller Center
 * Runs on seller-th.tiktok.com to extract orders and sync with DDCar
 * Zero ban risk: Only extracts data inside the seller's active local session.
 */

(function () {
  console.log("DDCar Sync Assistant: Initialized on TikTok Shop Seller Center");

  let isSyncing = false;

  function isTikTokOrderPage() {
    const href = window.location.href.toLowerCase();
    return href.includes("/order") || href.includes("order_id");
  }

  function isTikTokProductPage() {
    const href = window.location.href.toLowerCase();
    return href.includes("/product/manage") || href.includes("/product/list") || href.includes("/product");
  }

  // 1. Inject Floating Widget UI on TikTok
  function injectFloatingWidget() {
    if (document.getElementById("ddcar-sync-widget-tiktok")) return;

    const onProdPage = isTikTokProductPage();
    const onOrdPage = isTikTokOrderPage();

    const widget = document.createElement("div");
    widget.id = "ddcar-sync-widget-tiktok";
    widget.innerHTML = `
      <div id="ddcar-widget-header-tt" style="background: #000000; color: #ffffff; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
        <span style="font-weight: 700; font-size: 13px;">🚗 DDCar Sync (TikTok)</span>
        <span id="ddcar-widget-toggle-tt" style="cursor: pointer; font-size: 14px;">✕</span>
      </div>
      <div id="ddcar-widget-body-tt" style="padding: 10px 12px;">
        <div style="font-size: 11px; color: #e11d48; font-weight: 600; margin-bottom: 6px;">
          ⚫ ร้านค้า: TikTok Shop
        </div>
        <div id="ddcar-page-hint-tt" style="font-size: 11px; margin-bottom: 8px;">
          ${onProdPage 
            ? '<span style="color:#e11d48; font-weight:700;">📦 อยู่ที่หน้าจัดการสินค้า (Manage Products)</span>' 
            : onOrdPage 
              ? '<span style="color:#15803d; font-weight:600;">✓ อยู่ที่หน้ารายการคำสั่งซื้อแล้ว</span>' 
              : '<span style="color:#b45309; font-weight:600;">⚠️ อยู่ที่หน้าภาพรวม (Homepage)</span>'}
        </div>

        <!-- Sync Products Button (Catalog) -->
        <button id="ddcar-sync-catalog-tt" style="
          width: 100%;
          background: #e11d48;
          color: #fff;
          border: none;
          padding: 8px 12px;
          border-radius: 6px;
          font-weight: 700;
          font-size: 12px;
          cursor: pointer;
          margin-bottom: 6px;
          display: ${onProdPage ? 'block' : 'none'};
          box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        ">
          📦 ซิงค์แคตตาล็อกสินค้า TikTok (รูป + ลิงก์ตรง + ชื่อเต็ม)
        </button>

        <!-- Sync Orders Button -->
        <button id="ddcar-sync-btn-tt" style="
          width: 100%;
          background: #e11d48;
          color: #fff;
          border: none;
          padding: 8px 12px;
          border-radius: 6px;
          font-weight: 600;
          font-size: 12px;
          cursor: pointer;
          transition: background 0.2s;
          display: ${onProdPage ? 'none' : 'block'};
        ">
          🔄 ซิงค์ออเดอร์ TikTok เข้า DDCar
        </button>

        <!-- Navigation Buttons -->
        <button id="ddcar-goto-catalog-tt" style="
          width: 100%;
          background: #fff1f2;
          color: #be123c;
          border: 1px solid #fecdd3;
          padding: 6px 10px;
          border-radius: 6px;
          font-weight: 600;
          font-size: 11px;
          cursor: pointer;
          margin-top: 6px;
          display: ${onProdPage ? 'none' : 'block'};
        ">
          📦 ไปที่หน้าจัดการสินค้า (Manage Products)
        </button>

        <button id="ddcar-goto-orders-tt" style="
          width: 100%;
          background: #f8fafc;
          color: #334155;
          border: 1px solid #cbd5e1;
          padding: 6px 10px;
          border-radius: 6px;
          font-weight: 600;
          font-size: 11px;
          cursor: pointer;
          margin-top: 6px;
          display: ${onOrdPage ? 'none' : 'block'};
        ">
          👉 ไปที่หน้ารายการคำสั่งซื้อ (Orders)
        </button>

        <div id="ddcar-sync-status-tt" style="font-size: 10px; color: #64748b; margin-top: 6px; text-align: center;">
          พร้อมทำงาน
        </div>
      </div>
    `;

    // Widget Styles
    const style = document.createElement("style");
    style.textContent = `
      #ddcar-sync-widget-tiktok {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 999999;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.18);
        border: 1px solid #cbd5e1;
        width: 275px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        overflow: hidden;
      }
      #ddcar-sync-btn-tt:hover, #ddcar-sync-catalog-tt:hover {
        background: #be123c !important;
      }
      #ddcar-sync-btn-tt:disabled, #ddcar-sync-catalog-tt:disabled {
        background: #94a3b8 !important;
        cursor: not-allowed;
      }
    `;
    document.head.appendChild(style);
    document.body.appendChild(widget);

    // Toggle button
    document.getElementById("ddcar-widget-toggle-tt").addEventListener("click", () => {
      const body = document.getElementById("ddcar-widget-body-tt");
      if (body.style.display === "none") {
        body.style.display = "block";
        document.getElementById("ddcar-widget-toggle-tt").textContent = "✕";
      } else {
        body.style.display = "none";
        document.getElementById("ddcar-widget-toggle-tt").textContent = "▲";
      }
    });

    // Navigation buttons
    document.getElementById("ddcar-goto-orders-tt").addEventListener("click", () => {
      window.location.href = "https://seller-th.tiktok.com/order";
    });

    document.getElementById("ddcar-goto-catalog-tt").addEventListener("click", () => {
      window.location.href = "https://seller-th.tiktok.com/product/manage";
    });

    // Sync buttons
    document.getElementById("ddcar-sync-btn-tt").addEventListener("click", () => {
      performSync("manual_button");
    });

    document.getElementById("ddcar-sync-catalog-tt").addEventListener("click", () => {
      performTikTokCatalogSync("manual_button");
    });
  }

  // 2. Parse TikTok Orders from DOM
  function parseTikTokOrders() {
    const orders = [];
    const seenOrderSns = new Set();

    document.querySelectorAll("body *").forEach((el) => {
      const text = el.innerText || "";
      const m = text.match(/\b(5\d{15,18}|\d{16,19})\b/);
      if (m && !seenOrderSns.has(m[1])) {
        const orderSn = m[1];
        seenOrderSns.add(orderSn);

        // Climb up to find the full order card container without crossing into adjacent orders
        let container = el;
        let bestContainer = el;
        for (let i = 0; i < 15 && container.parentElement && container.parentElement !== document.body; i++) {
          const parent = container.parentElement;
          const pText = parent.innerText || "";
          const allSns = pText.match(/\b(5\d{15,18}|\d{16,19})\b/g) || [];
          const uniqueSns = new Set(allSns);
          if (uniqueSns.size > 1) {
            // Reached parent table containing multiple orders, stop here
            break;
          }
          container = parent;
          bestContainer = parent;
        }

        const items = extractItemsFromTikTokContainer(bestContainer, orderSn);
        orders.push({
          order_sn: orderSn,
          order_date: new Date().toISOString(),
          order_status: "processed",
          buyer_username: "",
          total_amount: extractAmountFromTikTok(container),
          items: items
        });
      }
    });

    console.log(`DDCar Extracted ${orders.length} orders from TikTok DOM`);
    return orders;
  }

  function extractAmountFromTikTok(container) {
    if (!container) return 0;
    const txt = container.innerText || "";
    const m = txt.match(/(?:฿|THB)\s*([0-9,]+(\.[0-9]{2})?)/i);
    if (m) {
      return parseFloat(m[1].replace(/,/g, ""));
    }
    return 0;
  }

  function extractItemsFromTikTokContainer(container, orderSn) {
    const items = [];
    if (!container) return items;

    // Check if container has distinct item row elements
    const candidateItemEls = container.querySelectorAll("tr, [class*='order-item'], [class*='product-item'], [class*='goods-item'], [class*='package-item']");
    const itemElements = [];

    candidateItemEls.forEach((el) => {
      const txt = el.innerText || "";
      if ((txt.includes("฿") || txt.includes("THB") || /x\s*\d+/i.test(txt)) && txt.length > 15) {
        let isParent = false;
        for (const other of candidateItemEls) {
          if (other !== el && el.contains(other)) {
            isParent = true;
            break;
          }
        }
        if (!isParent) itemElements.push(el);
      }
    });

    const processTextLines = (lines, el) => {
      let currentSku = "";
      let currentTitle = "";
      let currentQty = 1;
      let currentPrice = 0;
      let productId = "";
      let imageUrl = "";
      let productUrl = "";
      const variations = [];

      // Extract image and link from DOM element
      if (el) {
        const imgEl = el.querySelector("img");
        if (imgEl && imgEl.src && !imgEl.src.includes("data:image/svg")) {
          imageUrl = imgEl.src;
        }
        const linkEl = el.querySelector("a[href*='product'], a[href*='goods'], a[href*='item']");
        if (linkEl && linkEl.href) {
          productUrl = linkEl.href;
        }
      }

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Seller SKU
        if (/^(SKU|SKU ผู้ขาย|รหัสสินค้า|Seller SKU)\s*[:：]/i.test(line)) {
          currentSku = line.replace(/^(SKU|SKU ผู้ขาย|รหัสสินค้า|Seller SKU)\s*[:：]\s*/i, "").trim();
          continue;
        }

        // Product ID (TikTok 19-digit number)
        const idMatch = line.match(/\b(17\d{16,19})\b/) || line.match(/ID\s*[:：]?\s*([0-9]{15,22})/i);
        if (idMatch && !productId) {
          productId = idMatch[1];
        }

        // Variations & Specifications (e.g. ข้อมูลจำเพาะ SKU:, Specification:, ตัวเลือก:, สี:, รุ่น:, etc.)
        const varMatch = line.match(/^(ข้อมูลจำเพาะ SKU|ข้อมูลจำเพาะ|ตัวเลือก|สี|รุ่น|แบบ|ลาย|ขนาด|ข้าง|ชนิด|Specification|SKU Specification|Variation|Color|Size|Type|Model)\s*[:：]\s*(.+)/i);
        if (varMatch) {
          variations.push(varMatch[2].trim());
          continue;
        }

        // Bracketed variation like [Logo แดง] or [สีส้ม, ข้างซ้าย]
        if (/^\[.+\]$/.test(line) && !line.includes("฿") && !line.includes("THB")) {
          variations.push(line.replace(/^\[|\]$/g, "").trim());
          continue;
        }

        // Quantity
        const qtyMatch = line.match(/^x\s*([0-9]+)$/i) || line.match(/จำนวน\s*[:：]\s*([0-9]+)/);
        if (qtyMatch) {
          currentQty = parseInt(qtyMatch[1], 10) || 1;
          continue;
        }

        // Price
        const priceMatch = line.match(/(?:฿|THB)\s*([0-9,]+(\.[0-9]{2})?)/i);
        if (priceMatch && !currentPrice) {
          currentPrice = parseFloat(priceMatch[1].replace(/,/g, "")) || 0;
          continue;
        }

        // Product Title (preserves complete text, does not truncate)
        if (!currentTitle && line.length >= 6 && 
            !line.startsWith("฿") && !line.startsWith("THB") && 
            !line.startsWith("SKU") && !line.startsWith("ID ") &&
            !/^(จัดส่งสำเร็จ|รอขนส่ง|กำลังจัดส่ง|ยืนยันรับแล้ว|ที่ต้องจัดส่ง|ยกเลิกแล้ว|คืนเงิน|เสร็จสิ้น|คำสั่งซื้อ|รายละเอียด|พิมพ์ใบเสร็จ)/.test(line) &&
            !/^\d{15,20}$/.test(line)) {
          currentTitle = line;
        }
      }

      if (!currentSku) {
        if (productId) {
          currentSku = `(ไม่พบ Seller SKU) ID ${productId}`;
        } else if (currentTitle) {
          currentSku = "NO_SKU";
        }
      }

      if (productId && !productUrl) {
        productUrl = `https://seller-th.tiktok.com/product/edit/${productId}`;
      }

      let fullPlatformName = currentTitle || `สินค้า TikTok ${orderSn}`;
      const uniqueVars = Array.from(new Set(variations.filter((v) => v.length > 0)));
      if (uniqueVars.length > 0) {
        const varText = uniqueVars.join(", ");
        if (!fullPlatformName.includes(varText) && !fullPlatformName.includes(`[${varText}]`)) {
          fullPlatformName = `${fullPlatformName} [ตัวเลือก: ${varText}]`;
        }
      }

      return {
        platform_sku: currentSku,
        platform_name: fullPlatformName,
        image_url: imageUrl,
        product_url: productUrl,
        qty: currentQty,
        price: currentPrice
      };
    };

    if (itemElements.length > 0) {
      itemElements.forEach((el) => {
        const lines = (el.innerText || "").split("\n").map((l) => l.trim()).filter((l) => l.length > 0);
        const item = processTextLines(lines, el);
        if (item && item.platform_name) items.push(item);
      });
    } else {
      const lines = container.innerText.split("\n").map((l) => l.trim()).filter((l) => l.length > 0);
      const item = processTextLines(lines, container);
      if (item) items.push(item);
    }

    return items;
  }

  // 3. Perform Sync (Returns Promise for message responder)
  function performSync(triggerSource) {
    return new Promise((resolve, reject) => {
      if (isSyncing) {
        resolve({ success: false, message: "กำลังซิงค์อยู่แล้ว กรุณารอสักครู่" });
        return;
      }
      isSyncing = true;

      const btn = document.getElementById("ddcar-sync-btn-tt");
      const status = document.getElementById("ddcar-sync-status-tt");
      const gotoBtn = document.getElementById("ddcar-goto-orders-tt");

      if (btn) {
        btn.disabled = true;
        btn.textContent = "⏳ กำลังดึงข้อมูล TikTok...";
      }
      if (status) status.textContent = "กำลังรวบรวมคำสั่งซื้อ...";

      if (!isTikTokOrderPage()) {
        isSyncing = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = "🔄 ซิงค์ออเดอร์ TikTok เข้า DDCar";
        }
        if (status) {
          status.innerHTML = `<span style="color:#b45309;">⚠️ คุณอยู่ที่หน้าภาพรวม กรุณากดปุ่ม <strong>'ไปที่หน้ารายการคำสั่งซื้อ TikTok'</strong> ด้านล่าง</span>`;
        }
        if (gotoBtn) gotoBtn.style.display = "block";

        resolve({
          success: false,
          isDashboard: true,
          platform: "TikTok Shop",
          orderUrl: "https://seller-th.tiktok.com/order",
          message: "ขณะนี้อยู่ที่หน้าแรก (ภาพรวมร้านค้า) กรุณาไปที่หน้ารายการคำสั่งซื้อ"
        });
        return;
      }

      const orders = parseTikTokOrders();

      if (orders.length === 0) {
        isSyncing = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = "🔄 ซิงค์ออเดอร์ TikTok เข้า DDCar";
        }
        if (status) {
          status.innerHTML = `<span style="color:#eab308;">⚠️ ยังไม่พบรายการออเดอร์ในตารางหน้านี้</span>`;
        }
        resolve({
          success: false,
          isDashboard: false,
          platform: "TikTok Shop",
          ordersCount: 0,
          message: "ยังไม่พบรายการออเดอร์ในตารางหน้านี้ กรุณารอให้หน้าเว็บโหลดตารางเสร็จแล้วกดใหม่"
        });
        return;
      }

      if (status) status.textContent = `พบ ${orders.length} ออเดอร์ กำลังส่งเข้า DDCar...`;

      const payload = {
        channel_code: "tiktok_main",
        source: triggerSource || "tiktok_page",
        orders: orders
      };

      chrome.runtime.sendMessage(
        { action: "SEND_ORDERS_TO_DDCAR", payload: payload },
        (response) => {
          if (chrome.runtime.lastError) {
            console.log("DDCar Sync message error:", chrome.runtime.lastError.message);
          }
          isSyncing = false;
          if (btn) {
            btn.disabled = false;
            btn.textContent = "🔄 ซิงค์ออเดอร์ TikTok เข้า DDCar";
          }

          if (response && response.success) {
            const resData = response.data;
            if (status) {
              status.innerHTML = `<span style="color:#10b981;">✅ ซิงค์ TikTok สำเร็จ ${resData.orders_processed || orders.length} ออเดอร์ (${resData.items_processed || orders.length} รายการ)!</span>`;
            }
            resolve({
              success: true,
              platform: "TikTok Shop",
              ordersCount: resData.orders_processed || orders.length,
              itemsCount: resData.items_processed || orders.length,
              message: `ซิงค์ TikTok สำเร็จ ${resData.orders_processed || orders.length} ออเดอร์ (${resData.items_processed || orders.length} รายการ) เข้า DDCar เรียบร้อย!`
            });
          } else {
            const errMsg = (response && response.error) || "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ DDCar ได้";
            if (status) {
              status.innerHTML = `<span style="color:#ef4444;">❌ ล้มเหลว: ${errMsg}</span>`;
            }
            resolve({
              success: false,
              platform: "TikTok Shop",
              message: errMsg
            });
          }
        }
      );
    });
  }

  // 3. Parse Product Catalog from TikTok Shop Seller Center
  function parseTikTokProducts() {
    const products = [];
    const seen = new Set();

    const rows = document.querySelectorAll(".arco-table-tr, tr[data-row-key], table tbody tr, [class*='product-item']");

    rows.forEach((row) => {
      // 1. Image
      let img = "";
      const imgEl = row.querySelector("img[src*='ibyteimg.com'], img.arco-image-img, img");
      if (imgEl && imgEl.src) {
        let src = imgEl.src;
        if (src.startsWith("//")) src = "https:" + src;
        if (src.includes("ibyteimg.com")) img = src;
      }

      // 2. Title & ID
      let title = "";
      let productUrl = "";
      let itemId = "";

      const linkEl = row.querySelector("a[href*='/product/edit/'], [class*='product-title'], [class*='product-name'] a, a[title]");
      if (linkEl) {
        title = linkEl.getAttribute("title") || linkEl.innerText.trim();
        const href = linkEl.getAttribute("href") || "";
        const m = href.match(/\/product\/edit\/(\d{15,22})/);
        if (m) {
          itemId = m[1];
          productUrl = "https://seller-th.tiktok.com/product/edit/" + itemId;
        }
      }

      if (!title) {
        const titleEl = row.querySelector("[class*='product-title'], [class*='product-name'], .title");
        if (titleEl) {
          title = titleEl.getAttribute("title") || titleEl.innerText.trim();
        }
      }

      if (!title) return;

      title = title.replace(/\s{2,}/g, " ").trim();

      // Check if itemId is in row text
      if (!itemId) {
        const rowText = row.innerText || "";
        const m = rowText.match(/\b(17\d{17})\b/);
        if (m) {
          itemId = m[1];
          productUrl = "https://seller-th.tiktok.com/product/edit/" + itemId;
        }
      }

      // 3. Find Seller SKU & Variations
      const foundSkus = [];
      row.querySelectorAll("*").forEach((el) => {
        const txt = el.innerText ? el.innerText.trim() : "";
        if (txt.startsWith("Seller SKU:") || txt.startsWith("SKU:")) {
          const parts = txt.split(/:\s*/);
          if (parts[1] && parts[1].length > 1) foundSkus.push(parts[1].trim());
        }
      });

      if (foundSkus.length === 0) {
        const rowText = row.innerText || "";
        const lines = rowText.split("\n").map((l) => l.trim()).filter(Boolean);
        for (const line of lines) {
          if (line.match(/^[A-Z0-9\-_]{4,25}$/i) && !line.includes("THB") && !line.includes("TikTok")) {
            foundSkus.push(line);
            break;
          }
        }
      }

      if (foundSkus.length === 0 && itemId) {
        foundSkus.push("(ไม่พบ Seller SKU) ID " + itemId);
      }

      if (!productUrl && title) {
        productUrl = "https://seller-th.tiktok.com/product/manage?keyword=" + encodeURIComponent(title.substring(0, 35));
      }

      if (foundSkus.length === 0) {
        foundSkus.push("");
      }

      foundSkus.forEach((sku) => {
        const key = sku + "|" + title;
        if (!seen.has(key)) {
          seen.add(key);
          products.push({
            channel_code: "tiktok_main",
            platform_sku: sku,
            platform_name: title,
            image_url: img,
            product_url: productUrl,
            item_id: itemId
          });
        }
      });
    });

    return products;
  }

  function performTikTokCatalogSync(triggerSource) {
    return new Promise((resolve) => {
      const btn = document.getElementById("ddcar-sync-catalog-tt");
      const status = document.getElementById("ddcar-sync-status-tt");

      if (btn) {
        btn.disabled = true;
        btn.textContent = "⏳ กำลังดึงแคตตาล็อกสินค้า...";
      }
      if (status) status.textContent = "กำลังรวบรวมข้อมูลสินค้าและรูปภาพจากตาราง...";

      const products = parseTikTokProducts();

      if (products.length === 0) {
        if (btn) {
          btn.disabled = false;
          btn.textContent = "📦 ซิงค์แคตตาล็อกสินค้า TikTok";
        }
        if (status) {
          status.innerHTML = `<span style="color:#eab308;">⚠️ ยังไม่พบตารางสินค้าในหน้านี้ (กรุณารอโหลดตารางเสร็จ)</span>`;
        }
        resolve({ success: false, message: "ไม่พบตารางสินค้าในหน้านี้" });
        return;
      }

      if (status) status.textContent = `พบ ${products.length} รายการ กำลังอัพเดทเข้า DDCar...`;

      const payload = {
        channel_code: "tiktok_main",
        source: triggerSource || "tiktok_catalog_page",
        products: products
      };

      chrome.runtime.sendMessage(
        { action: "SEND_CATALOG_TO_DDCAR", payload: payload },
        (response) => {
          if (chrome.runtime.lastError) {
            console.log("DDCar Sync message error:", chrome.runtime.lastError.message);
          }
          if (btn) {
            btn.disabled = false;
            btn.textContent = "📦 ซิงค์แคตตาล็อกสินค้า TikTok (เรียบร้อย)";
          }

          if (response && response.success) {
            const data = response.data || {};
            const msg = `✅ ซิงค์แคตตาล็อก TikTok สำเร็จ ${data.total_processed || products.length} รายการ (อัพเดท ${data.updated_count || 0} / เพิ่มใหม่ ${data.inserted_count || 0})!`;
            if (status) {
              status.innerHTML = `<span style="color:#10b981; font-weight:700;">${msg}</span>`;
            }
            resolve({ success: true, message: msg, count: products.length });
          } else {
            const err = (response && response.error) || "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ DDCar ได้";
            if (status) {
              status.innerHTML = `<span style="color:#ef4444;">❌ ล้มเหลว: ${err}</span>`;
            }
            resolve({ success: false, message: err });
          }
        }
      );
    });
  }

  // 4. Listen for background & popup auto-sync signals (ASYNC WITH return true!)
  if (chrome && chrome.runtime && chrome.runtime.onMessage) {
    chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
      if (request.action === "START_AUTO_SYNC") {
        console.log("DDCar: Received START_AUTO_SYNC for TikTok", request.source);
        performSync(request.source)
          .then((res) => sendResponse(res))
          .catch((err) => sendResponse({ success: false, platform: "TikTok Shop", message: err.message }));
        return true;
      } else if (request.action === "START_CATALOG_SYNC") {
        console.log("DDCar: Received START_CATALOG_SYNC for TikTok", request.source);
        performTikTokCatalogSync(request.source)
          .then((res) => sendResponse(res))
          .catch((err) => sendResponse({ success: false, platform: "TikTok Shop", message: err.message }));
        return true;
      } else if (request.action === "NAVIGATE_TO_ORDERS") {
        window.location.href = "https://seller-th.tiktok.com/order";
        sendResponse({ success: true });
        return true;
      } else if (request.action === "NAVIGATE_TO_CATALOG") {
        window.location.href = "https://seller-th.tiktok.com/product/manage";
        sendResponse({ success: true });
        return true;
      }
    });
  }

  // Initialize UI after page loads
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", injectFloatingWidget);
  } else {
    injectFloatingWidget();
  }
})();
