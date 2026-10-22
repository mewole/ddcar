/**
 * DDCar Sync Assistant - Content Script for Lazada Seller Center
 * Runs on sellercenter.lazada.co.th to extract orders and sync with DDCar
 * Zero ban risk: Only extracts data inside the seller's active local session.
 */

(function () {
  console.log("DDCar Sync Assistant: Initialized on Lazada Seller Center");

  let isSyncing = false;

  function isLazadaOrderPage() {
    const href = window.location.href.toLowerCase();
    const hash = window.location.hash.toLowerCase();
    return href.includes("/order") || hash.includes("order") || href.includes("tradeorderid");
  }

  function isLazadaProductPage() {
    const href = window.location.href.toLowerCase();
    const hash = window.location.hash.toLowerCase();
    return href.includes("/product/list") || href.includes("/apps/product") || hash.includes("product");
  }

  // 1. Inject Floating Widget UI on Lazada
  function injectFloatingWidget() {
    if (document.getElementById("ddcar-sync-widget-lazada")) return;

    const onProdPage = isLazadaProductPage();
    const onOrdPage = isLazadaOrderPage();

    const widget = document.createElement("div");
    widget.id = "ddcar-sync-widget-lazada";
    widget.innerHTML = `
      <div id="ddcar-widget-header-laz" style="background: #0f172a; color: #ffffff; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
        <span style="font-weight: 700; font-size: 13px;">🚗 DDCar Sync (Lazada)</span>
        <span id="ddcar-widget-toggle-laz" style="cursor: pointer; font-size: 14px;">✕</span>
      </div>
      <div id="ddcar-widget-body-laz" style="padding: 10px 12px;">
        <div style="font-size: 11px; color: #0284c7; font-weight: 600; margin-bottom: 6px;">
          🔵 ร้านค้า: Lazada Main (Lazada Mig)
        </div>
        <div id="ddcar-page-hint-laz" style="font-size: 11px; margin-bottom: 8px;">
          ${onProdPage 
            ? '<span style="color:#0284c7; font-weight:700;">📦 อยู่ที่หน้ารายการสินค้า (Product List)</span>' 
            : onOrdPage 
              ? '<span style="color:#15803d; font-weight:600;">✓ อยู่ที่หน้ารายการคำสั่งซื้อแล้ว</span>' 
              : '<span style="color:#b45309; font-weight:600;">⚠️ อยู่ที่หน้าภาพรวม (Dashboard)</span>'}
        </div>

        <!-- Sync Products Button (Catalog) -->
        <button id="ddcar-sync-catalog-laz" style="
          width: 100%;
          background: #0284c7;
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
          📦 ซิงค์แคตตาล็อกสินค้า Lazada (รูป + ลิงก์ตรง + ชื่อเต็ม)
        </button>

        <!-- Sync Orders Button -->
        <button id="ddcar-sync-btn-laz" style="
          width: 100%;
          background: #0284c7;
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
          🔄 ซิงค์ออเดอร์ Lazada เข้า DDCar
        </button>

        <!-- Navigation Buttons -->
        <button id="ddcar-goto-catalog-laz" style="
          width: 100%;
          background: #f0f9ff;
          color: #0369a1;
          border: 1px solid #bae6fd;
          padding: 6px 10px;
          border-radius: 6px;
          font-weight: 600;
          font-size: 11px;
          cursor: pointer;
          margin-top: 6px;
          display: ${onProdPage ? 'none' : 'block'};
        ">
          📦 ไปที่หน้าจัดการสินค้า (Product List)
        </button>

        <button id="ddcar-goto-orders-laz" style="
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

        <div id="ddcar-sync-status-laz" style="font-size: 10px; color: #64748b; margin-top: 6px; text-align: center;">
          พร้อมทำงาน
        </div>
      </div>
    `;

    // Widget Styles
    const style = document.createElement("style");
    style.textContent = `
      #ddcar-sync-widget-lazada {
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
      #ddcar-sync-btn-laz:hover, #ddcar-sync-catalog-laz:hover {
        background: #0369a1 !important;
      }
      #ddcar-sync-btn-laz:disabled, #ddcar-sync-catalog-laz:disabled {
        background: #94a3b8 !important;
        cursor: not-allowed;
      }
    `;
    document.head.appendChild(style);
    document.body.appendChild(widget);

    // Toggle button
    document.getElementById("ddcar-widget-toggle-laz").addEventListener("click", () => {
      const body = document.getElementById("ddcar-widget-body-laz");
      if (body.style.display === "none") {
        body.style.display = "block";
        document.getElementById("ddcar-widget-toggle-laz").textContent = "✕";
      } else {
        body.style.display = "none";
        document.getElementById("ddcar-widget-toggle-laz").textContent = "▲";
      }
    });

    // Navigation buttons
    document.getElementById("ddcar-goto-orders-laz").addEventListener("click", () => {
      window.location.href = "https://sellercenter.lazada.co.th/order/query";
    });

    document.getElementById("ddcar-goto-catalog-laz").addEventListener("click", () => {
      window.location.href = "https://sellercenter.lazada.co.th/apps/product/list";
    });

    // Sync buttons
    document.getElementById("ddcar-sync-btn-laz").addEventListener("click", () => {
      performSync("manual_button");
    });

    document.getElementById("ddcar-sync-catalog-laz").addEventListener("click", () => {
      performLazadaCatalogSync("manual_button");
    });
  }

  // 2. Parse Lazada Orders from DOM
  function parseLazadaOrders() {
    const orders = [];
    const seenOrderSns = new Set();

    // Look for Lazada order number patterns (14-17 digits e.g., 901234567890123)
    document.querySelectorAll("body *").forEach((el) => {
      const text = el.innerText || "";
      const m = text.match(/\b(9\d{14,16}|\d{15,17})\b/);
      if (m && !seenOrderSns.has(m[1])) {
        const orderSn = m[1];
        seenOrderSns.add(orderSn);

        // Climb up to find the full order card container without crossing into adjacent orders
        let container = el;
        let bestContainer = el;
        for (let i = 0; i < 15 && container.parentElement && container.parentElement !== document.body; i++) {
          const parent = container.parentElement;
          const pText = parent.innerText || "";
          const allSns = pText.match(/\b(9\d{14,16}|\d{15,17})\b/g) || [];
          const uniqueSns = new Set(allSns);
          if (uniqueSns.size > 1) {
            break;
          }
          container = parent;
          bestContainer = parent;
        }

        const items = extractItemsFromLazadaContainer(bestContainer, orderSn);
        orders.push({
          order_sn: orderSn,
          order_date: new Date().toISOString(),
          order_status: "processed",
          buyer_username: "",
          total_amount: extractAmountFromLazada(container),
          items: items
        });
      }
    });

    console.log(`DDCar Extracted ${orders.length} orders from Lazada DOM`);
    return orders;
  }

  function extractAmountFromLazada(container) {
    if (!container) return 0;
    const txt = container.innerText || "";
    const m = txt.match(/(?:฿|THB)\s*([0-9,]+(\.[0-9]{2})?)/i);
    if (m) {
      return parseFloat(m[1].replace(/,/g, ""));
    }
    return 0;
  }

  function extractItemsFromLazadaContainer(container, orderSn) {
    const items = [];
    if (!container) return items;

    const candidateItemEls = container.querySelectorAll("tr, [class*='package-item'], [class*='order-item'], [class*='product-item']");
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
      let imageUrl = "";
      let productUrl = "";
      const variations = [];

      // Extract image & link from element
      if (el) {
        const imgEl = el.querySelector("img");
        if (imgEl && imgEl.src && !imgEl.src.includes("data:image/svg")) {
          imageUrl = imgEl.src;
        }
        const linkEl = el.querySelector("a[href*='product'], a[href*='item'], a[href*='detail']");
        if (linkEl && linkEl.href) {
          productUrl = linkEl.href;
        }
      }

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Seller SKU in Lazada: "Seller SKU: ..." or "SKU:" or "SKU ผู้ขาย:"
        if (/^(Seller SKU|SKU|SKU ผู้ขาย|รหัสสินค้า)\s*[:：]/i.test(line)) {
          currentSku = line.replace(/^(Seller SKU|SKU|SKU ผู้ขาย|รหัสสินค้า)\s*[:：]\s*/i, "").trim();
          continue;
        }

        // Variation / Specification in Lazada (e.g. Color Family, Specification, Variation, สี, รุ่น, etc.)
        const varMatch = line.match(/^(Color Family|Color|Specification|Variation|ตัวเลือก|สี|รุ่น|แบบ|ลาย|ขนาด|ข้าง|ชนิด)\s*[:：]\s*(.+)/i);
        if (varMatch) {
          variations.push(varMatch[2].trim());
          continue;
        }

        if (/^\[.+\]$/.test(line) && !line.includes("฿") && !line.includes("THB")) {
          variations.push(line.replace(/^\[|\]$/g, "").trim());
          continue;
        }

        // Quantity in Lazada: "x 1" or "จำนวน: 1"
        const qtyMatch = line.match(/^x\s*([0-9]+)$/i) || line.match(/จำนวน\s*[:：]\s*([0-9]+)/);
        if (qtyMatch) {
          currentQty = parseInt(qtyMatch[1], 10) || 1;
          continue;
        }

        // Price in Lazada
        const priceMatch = line.match(/(?:฿|THB)\s*([0-9,]+(\.[0-9]{2})?)/i);
        if (priceMatch && !currentPrice) {
          currentPrice = parseFloat(priceMatch[1].replace(/,/g, "")) || 0;
          continue;
        }

        // Product Title
        if (!currentTitle && line.length >= 6 && 
            !line.startsWith("฿") && !line.startsWith("THB") && 
            !line.startsWith("Seller SKU") && !line.startsWith("SKU") &&
            !/^(จัดส่งสำเร็จ|รอจัดส่ง|ยืนยันรับแล้ว|ที่ต้องจัดส่ง|ยกเลิกแล้ว|คืนเงิน|เสร็จสิ้น|คำสั่งซื้อ|รายละเอียด|พิมพ์ใบเสร็จ)/.test(line) &&
            !/^\d{15,20}$/.test(line)) {
          currentTitle = line;
        }
      }

      if (!currentSku && currentTitle) {
        currentSku = "NO_SKU";
      }

      let fullPlatformName = currentTitle || `สินค้า Lazada ${orderSn}`;
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

      const btn = document.getElementById("ddcar-sync-btn-laz");
      const status = document.getElementById("ddcar-sync-status-laz");
      const gotoBtn = document.getElementById("ddcar-goto-orders-laz");

      if (btn) {
        btn.disabled = true;
        btn.textContent = "⏳ กำลังดึงข้อมูล Lazada...";
      }
      if (status) status.textContent = "กำลังรวบรวมคำสั่งซื้อ...";

      // Check if on Dashboard instead of order page
      if (!isLazadaOrderPage()) {
        isSyncing = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = "🔄 ซิงค์ออเดอร์ Lazada เข้า DDCar";
        }
        if (status) {
          status.innerHTML = `<span style="color:#b45309;">⚠️ คุณอยู่ที่หน้าภาพรวม กรุณากดปุ่ม <strong>'ไปที่หน้ารายการคำสั่งซื้อ'</strong> ด้านล่าง</span>`;
        }
        if (gotoBtn) gotoBtn.style.display = "block";

        resolve({
          success: false,
          isDashboard: true,
          platform: "Lazada",
          orderUrl: "https://sellercenter.lazada.co.th/order/query",
          message: "ขณะนี้อยู่ที่หน้าแรก (ภาพรวมร้านค้า) ซึ่งยังไม่มีตารางออเดอร์ กรุณาไปที่หน้ารายการคำสั่งซื้อ"
        });
        return;
      }

      const orders = parseLazadaOrders();

      if (orders.length === 0) {
        isSyncing = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = "🔄 ซิงค์ออเดอร์ Lazada เข้า DDCar";
        }
        if (status) {
          status.innerHTML = `<span style="color:#eab308;">⚠️ ยังไม่พบรายการออเดอร์ในตารางหน้านี้ (อาจกำลังโหลดตาราง)</span>`;
        }
        resolve({
          success: false,
          isDashboard: false,
          platform: "Lazada",
          ordersCount: 0,
          message: "ยังไม่พบรายการออเดอร์ในตารางหน้านี้ กรุณารอให้หน้าเว็บโหลดตารางเสร็จแล้วกดใหม่"
        });
        return;
      }

      if (status) status.textContent = `พบ ${orders.length} ออเดอร์ กำลังส่งเข้า DDCar...`;

      const payload = {
        channel_code: "lazada_main",
        source: triggerSource || "lazada_page",
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
            btn.textContent = "🔄 ซิงค์ออเดอร์ Lazada เข้า DDCar";
          }

          if (response && response.success) {
            const resData = response.data;
            if (status) {
              status.innerHTML = `<span style="color:#10b981;">✅ ซิงค์ Lazada สำเร็จ ${resData.orders_processed || orders.length} ออเดอร์ (${resData.items_processed || orders.length} รายการ)!</span>`;
            }
            resolve({
              success: true,
              platform: "Lazada",
              ordersCount: resData.orders_processed || orders.length,
              itemsCount: resData.items_processed || orders.length,
              message: `ซิงค์ Lazada สำเร็จ ${resData.orders_processed || orders.length} ออเดอร์ (${resData.items_processed || orders.length} รายการ) เข้า DDCar เรียบร้อย!`
            });
          } else {
            const errMsg = (response && response.error) || "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ DDCar ได้";
            if (status) {
              status.innerHTML = `<span style="color:#ef4444;">❌ ล้มเหลว: ${errMsg}</span>`;
            }
            resolve({
              success: false,
              platform: "Lazada",
              message: errMsg
            });
          }
        }
      );
    });
  }

  // 3. Parse Product Catalog from Lazada Seller Center
  function parseLazadaProducts() {
    const products = [];
    const seen = new Set();

    const rows = document.querySelectorAll(".next-table-row, tbody tr, [class*='table-row']");

    rows.forEach((row) => {
      // 1. Image
      let img = "";
      const imgEl = row.querySelector("img[src*='slatic.net'], img");
      if (imgEl && imgEl.src) {
        let src = imgEl.src;
        if (src.startsWith("//")) src = "https:" + src;
        src = src.replace(/_\d+x\d+[^.]*\./, ".");
        if (src.includes("slatic.net")) img = src;
      }

      // 2. Title & Direct URL
      let title = "";
      let productUrl = "";
      let itemId = "";

      const linkEl = row.querySelector("a[href*='lazada.co.th/products/'], a.product-title, [class*='product-title'] a, a[title]");
      if (linkEl) {
        title = linkEl.getAttribute("title") || linkEl.innerText.trim();
        productUrl = linkEl.href || "";
        const mId = productUrl.match(/i([0-9]{8,15})/);
        if (mId) itemId = mId[1];
      }

      if (!title) {
        const titleEl = row.querySelector("[class*='product-name'], [class*='product-title'], .title");
        if (titleEl) {
          title = titleEl.getAttribute("title") || titleEl.innerText.trim();
        }
      }

      if (!title) return;

      // Clean variation or brackets in title
      title = title.replace(/\s{2,}/g, " ").trim();

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
          if (line.match(/^[A-Z0-9\-_]{4,25}$/i) && !line.includes("THB") && !line.includes("Lazada")) {
            foundSkus.push(line);
            break;
          }
        }
      }

      if (foundSkus.length === 0 && itemId) {
        foundSkus.push("(ไม่พบ Seller SKU) ID " + itemId);
      }

      if (!productUrl && title) {
        productUrl = "https://www.lazada.co.th/catalog/?q=" + encodeURIComponent(title.substring(0, 35));
      }

      if (foundSkus.length === 0) {
        foundSkus.push("");
      }

      foundSkus.forEach((sku) => {
        const key = sku + "|" + title;
        if (!seen.has(key)) {
          seen.add(key);
          products.push({
            channel_code: "lazada_main",
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

  function performLazadaCatalogSync(triggerSource) {
    return new Promise((resolve) => {
      const btn = document.getElementById("ddcar-sync-catalog-laz");
      const status = document.getElementById("ddcar-sync-status-laz");

      if (btn) {
        btn.disabled = true;
        btn.textContent = "⏳ กำลังดึงแคตตาล็อกสินค้า...";
      }
      if (status) status.textContent = "กำลังรวบรวมข้อมูลสินค้าและรูปภาพจากตาราง...";

      const products = parseLazadaProducts();

      if (products.length === 0) {
        if (btn) {
          btn.disabled = false;
          btn.textContent = "📦 ซิงค์แคตตาล็อกสินค้า Lazada";
        }
        if (status) {
          status.innerHTML = `<span style="color:#eab308;">⚠️ ยังไม่พบตารางสินค้าในหน้านี้ (กรุณารอโหลดตารางเสร็จ)</span>`;
        }
        resolve({ success: false, message: "ไม่พบตารางสินค้าในหน้านี้" });
        return;
      }

      if (status) status.textContent = `พบ ${products.length} รายการ กำลังอัพเดทเข้า DDCar...`;

      const payload = {
        channel_code: "lazada_main",
        source: triggerSource || "lazada_catalog_page",
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
            btn.textContent = "📦 ซิงค์แคตตาล็อกสินค้า Lazada (เรียบร้อย)";
          }

          if (response && response.success) {
            const data = response.data || {};
            const msg = `✅ ซิงค์แคตตาล็อกสำเร็จ ${data.total_processed || products.length} รายการ (อัพเดท ${data.updated_count || 0} / เพิ่มใหม่ ${data.inserted_count || 0})!`;
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
        console.log("DDCar: Received START_AUTO_SYNC for Lazada", request.source);
        performSync(request.source)
          .then((res) => sendResponse(res))
          .catch((err) => sendResponse({ success: false, platform: "Lazada", message: err.message }));
        return true;
      } else if (request.action === "START_CATALOG_SYNC") {
        console.log("DDCar: Received START_CATALOG_SYNC for Lazada", request.source);
        performLazadaCatalogSync(request.source)
          .then((res) => sendResponse(res))
          .catch((err) => sendResponse({ success: false, platform: "Lazada", message: err.message }));
        return true;
      } else if (request.action === "NAVIGATE_TO_ORDERS") {
        window.location.href = "https://sellercenter.lazada.co.th/order/query";
        sendResponse({ success: true });
        return true;
      } else if (request.action === "NAVIGATE_TO_CATALOG") {
        window.location.href = "https://sellercenter.lazada.co.th/apps/product/list";
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
