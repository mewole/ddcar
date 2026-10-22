/**
 * DDCar Sync Assistant - Content Script for Shopee Seller Centre
 * Runs on seller.shopee.co.th to extract orders and sync with DDCar
 * Zero ban risk: Only extracts data inside the seller's active local session.
 */

(function () {
  console.log("DDCar Sync Assistant: Initialized on Shopee Seller Centre");

  let isSyncing = false;

  function isShopeeOrderPage() {
    const href = window.location.href.toLowerCase();
    return href.includes("/portal/sale") || href.includes("/order");
  }

  // 1. Inject Floating Widget UI
  function injectFloatingWidget() {
    if (document.getElementById("ddcar-sync-widget")) return;

    const widget = document.createElement("div");
    widget.id = "ddcar-sync-widget";
    widget.innerHTML = `
      <div id="ddcar-widget-header" style="background: #1e293b; color: #ffffff; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
        <span style="font-weight: 700; font-size: 13px;">🚗 DDCar Sync (Shopee)</span>
        <span id="ddcar-widget-toggle" style="cursor: pointer; font-size: 14px;">✕</span>
      </div>
      <div id="ddcar-widget-body" style="padding: 10px 12px;">
        <div id="ddcar-shop-label" style="font-size: 11px; color: #64748b; margin-bottom: 6px;">
          กำลังตรวจสอบบัญชีร้านค้า...
        </div>
        <div id="ddcar-page-hint-sp" style="font-size: 11px; margin-bottom: 8px;">
          ${isShopeeOrderPage() 
            ? '<span style="color:#15803d; font-weight:600;">✓ อยู่ที่หน้าคำสั่งซื้อแล้ว</span>' 
            : '<span style="color:#b45309; font-weight:600;">⚠️ อยู่ที่หน้าแรก กรุณาไปที่หน้าคำสั่งซื้อ</span>'}
        </div>
        <button id="ddcar-sync-btn" style="
          width: 100%;
          background: #ea580c;
          color: #fff;
          border: none;
          padding: 8px 12px;
          border-radius: 6px;
          font-weight: 600;
          font-size: 12px;
          cursor: pointer;
          transition: background 0.2s;
        ">
          🔄 ซิงค์ออเดอร์ Shopee เข้า DDCar
        </button>
        <button id="ddcar-goto-orders-sp" style="
          width: 100%;
          background: #fff7ed;
          color: #c2410c;
          border: 1px solid #fed7aa;
          padding: 7px 10px;
          border-radius: 6px;
          font-weight: 600;
          font-size: 11px;
          cursor: pointer;
          margin-top: 6px;
          display: ${isShopeeOrderPage() ? 'none' : 'block'};
        ">
          👉 ไปที่หน้ารายการคำสั่งซื้อของฉัน
        </button>
        <div id="ddcar-sync-status" style="font-size: 10px; color: #64748b; margin-top: 6px; text-align: center;">
          พร้อมทำงาน
        </div>
      </div>
    `;

    // Widget Styles
    const style = document.createElement("style");
    style.textContent = `
      #ddcar-sync-widget {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 999999;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.18);
        border: 1px solid #cbd5e1;
        width: 260px;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        overflow: hidden;
      }
      #ddcar-sync-btn:hover {
        background: #c2410c !important;
      }
      #ddcar-sync-btn:disabled {
        background: #94a3b8 !important;
        cursor: not-allowed;
      }
    `;
    document.head.appendChild(style);
    document.body.appendChild(widget);

    // Event listeners
    document.getElementById("ddcar-widget-toggle").addEventListener("click", () => {
      const body = document.getElementById("ddcar-widget-body");
      if (body.style.display === "none") {
        body.style.display = "block";
        document.getElementById("ddcar-widget-toggle").textContent = "✕";
      } else {
        body.style.display = "none";
        document.getElementById("ddcar-widget-toggle").textContent = "▲";
      }
    });

    document.getElementById("ddcar-goto-orders-sp").addEventListener("click", () => {
      window.location.href = "https://seller.shopee.co.th/portal/sale/order";
    });

    document.getElementById("ddcar-sync-btn").addEventListener("click", () => {
      performSync("manual_button");
    });

    // Update Shop Label from storage
    if (chrome && chrome.storage && chrome.storage.local) {
      chrome.storage.local.get(["channelCode"], (data) => {
        const code = data.channelCode || "shopee_kingkong";
        const shopName = code === "shopee_taengraberd" ? "Shopee (แต่งระเบิด)" : "Shopee (KingKong)";
        const label = document.getElementById("ddcar-shop-label");
        if (label) {
          label.innerHTML = `ร้านปัจจุบัน: <strong style="color:#0f172a;">${shopName}</strong>`;
        }
      });
    }
  }

  // 2. Parse Orders from Shopee Seller Centre DOM
  function parseShopeeOrders() {
    const orders = [];
    const seenOrderSns = new Set();

    document.querySelectorAll("body *").forEach((el) => {
      const text = el.innerText || "";
      const match = text.match(/\b(2[3-9][0-1][0-9][0-3][0-9][A-Z0-9]{6,12})\b/);
      if (match && !seenOrderSns.has(match[1])) {
        const orderSn = match[1];
        seenOrderSns.add(orderSn);

        // Climb up to find the full order card container without crossing into adjacent orders
        let container = el;
        let bestContainer = el;
        for (let i = 0; i < 15 && container.parentElement && container.parentElement !== document.body; i++) {
          const parent = container.parentElement;
          const pText = parent.innerText || "";
          const allSns = pText.match(/\b(2[3-9][0-1][0-9][0-3][0-9][A-Z0-9]{6,12})\b/g) || [];
          const uniqueSns = new Set(allSns);
          if (uniqueSns.size > 1) {
            break;
          }
          container = parent;
          bestContainer = parent;
        }

        const items = extractItemsFromContainer(bestContainer, orderSn);
        orders.push({
          order_sn: orderSn,
          order_date: new Date().toISOString(),
          order_status: "processed",
          buyer_username: "",
          total_amount: extractAmountFromContainer(container),
          items: items
        });
      }
    });

    console.log(`DDCar Extracted ${orders.length} orders from Shopee DOM`);
    return orders;
  }

  function extractAmountFromContainer(container) {
    if (!container) return 0;
    const txt = container.innerText || "";
    const m = txt.match(/฿\s?([0-9,]+(\.[0-9]{2})?)/);
    if (m) {
      return parseFloat(m[1].replace(/,/g, ""));
    }
    return 0;
  }

  function extractItemsFromContainer(container, orderSn) {
    const items = [];
    if (!container) return items;

    // Shopee DOM order items
    const candidateItemEls = container.querySelectorAll("tr, .order-item, [class*='order-item'], [class*='item-row']");
    const itemElements = [];

    candidateItemEls.forEach((el) => {
      const txt = el.innerText || "";
      if ((txt.includes("฿") || /x\s*\d+/i.test(txt)) && txt.length > 15) {
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
        const linkEl = el.querySelector("a[href*='product'], a[href*='portal/sale']");
        if (linkEl && linkEl.href) {
          productUrl = linkEl.href;
        }

        const modelEl = el.querySelector(".order-item__model, [class*='model-name'], [class*='variation']");
        if (modelEl && modelEl.innerText.trim()) {
          variations.push(modelEl.innerText.replace(/^(ตัวเลือกสินค้า|ตัวเลือก|Variation)\s*[:：]\s*/i, "").trim());
        }
      }

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        if (/^(SKU|รหัสสินค้า|SKU อ้างอิง|SKU ผู้ขาย)\s*[:：]/i.test(line)) {
          currentSku = line.replace(/^(SKU|รหัสสินค้า|SKU อ้างอิง|SKU ผู้ขาย)\s*[:：]\s*/i, "").trim();
          continue;
        }

        const varMatch = line.match(/^(ตัวเลือกสินค้า|ตัวเลือก|Variation|สี|รุ่น|แบบ|ลาย|ขนาด|ข้าง|ชนิด)\s*[:：]\s*(.+)/i);
        if (varMatch) {
          variations.push(varMatch[2].trim());
          continue;
        }

        if (/^\[.+\]$/.test(line) && !line.includes("฿")) {
          variations.push(line.replace(/^\[|\]$/g, "").trim());
          continue;
        }

        const qtyMatch = line.match(/^x\s*([0-9]+)$/i) || line.match(/จำนวน\s*[:：]\s*([0-9]+)/);
        if (qtyMatch) {
          currentQty = parseInt(qtyMatch[1], 10) || 1;
          continue;
        }

        const priceMatch = line.match(/^฿\s*([0-9,]+(\.[0-9]{2})?)$/) || line.match(/฿\s*([0-9,]+(\.[0-9]{2})?)/);
        if (priceMatch && !currentPrice) {
          currentPrice = parseFloat(priceMatch[1].replace(/,/g, "")) || 0;
          continue;
        }

        if (!currentTitle && line.length >= 6 && 
            !line.startsWith("฿") && !line.startsWith("SKU") && 
            !/^(จัดส่งสำเร็จ|รอจัดส่ง|ยืนยันรับแล้ว|ที่ต้องจัดส่ง|ยกเลิกแล้ว|คืนเงิน|เสร็จสิ้น|คำสั่งซื้อ|รายละเอียด|พิมพ์ใบเสร็จ)/.test(line) &&
            !/^\d{15,20}$/.test(line)) {
          currentTitle = line;
        }
      }

      if (!currentSku && currentTitle) {
        currentSku = "NO_SKU";
      }

      let fullPlatformName = currentTitle || `สินค้าคำสั่งซื้อ ${orderSn}`;
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

      const btn = document.getElementById("ddcar-sync-btn");
      const status = document.getElementById("ddcar-sync-status");
      const gotoBtn = document.getElementById("ddcar-goto-orders-sp");

      if (btn) {
        btn.disabled = true;
        btn.textContent = "⏳ กำลังดึงข้อมูล Shopee...";
      }
      if (status) status.textContent = "กำลังรวบรวมออเดอร์...";

      if (!isShopeeOrderPage()) {
        isSyncing = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = "🔄 ซิงค์ออเดอร์ Shopee เข้า DDCar";
        }
        if (status) {
          status.innerHTML = `<span style="color:#b45309;">⚠️ คุณอยู่ที่หน้าแรก กรุณากดปุ่ม <strong>'ไปที่หน้ารายการคำสั่งซื้อ'</strong> ด้านล่าง</span>`;
        }
        if (gotoBtn) gotoBtn.style.display = "block";

        resolve({
          success: false,
          isDashboard: true,
          platform: "Shopee",
          orderUrl: "https://seller.shopee.co.th/portal/sale/order",
          message: "ขณะนี้อยู่ที่หน้าแรก (ภาพรวมร้านค้า) กรุณาไปที่หน้าคำสั่งซื้อของฉัน"
        });
        return;
      }

      const orders = parseShopeeOrders();

      if (orders.length === 0) {
        isSyncing = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = "🔄 ซิงค์ออเดอร์ Shopee เข้า DDCar";
        }
        if (status) {
          status.innerHTML = `<span style="color:#eab308;">⚠️ ยังไม่พบรายการออเดอร์ในตารางหน้านี้</span>`;
        }
        resolve({
          success: false,
          isDashboard: false,
          platform: "Shopee",
          ordersCount: 0,
          message: "ยังไม่พบรายการออเดอร์ในตารางหน้านี้ กรุณารอให้หน้าเว็บโหลดตารางเสร็จแล้วกดใหม่"
        });
        return;
      }

      if (status) status.textContent = `พบ ${orders.length} ออเดอร์ กำลังส่งเข้า DDCar...`;

      chrome.storage.local.get(["channelCode"], (storage) => {
        const channelCode = storage.channelCode || "shopee_kingkong";

        const payload = {
          channel_code: channelCode,
          source: triggerSource || "shopee_page",
          orders: orders
        };

        chrome.runtime.sendMessage(
          { action: "SEND_ORDERS_TO_DDCAR", payload: payload },
          (response) => {
            isSyncing = false;
            if (btn) {
              btn.disabled = false;
              btn.textContent = "🔄 ซิงค์ออเดอร์ Shopee เข้า DDCar";
            }

            if (response && response.success) {
              const resData = response.data;
              if (status) {
                status.innerHTML = `<span style="color:#10b981;">✅ ซิงค์ Shopee สำเร็จ ${resData.orders_processed || orders.length} ออเดอร์ (${resData.items_processed || orders.length} รายการ)!</span>`;
              }
              resolve({
                success: true,
                platform: "Shopee",
                ordersCount: resData.orders_processed || orders.length,
                itemsCount: resData.items_processed || orders.length,
                message: `ซิงค์ Shopee สำเร็จ ${resData.orders_processed || orders.length} ออเดอร์ (${resData.items_processed || orders.length} รายการ) เข้า DDCar เรียบร้อย!`
              });
            } else {
              const errMsg = (response && response.error) || "ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ DDCar ได้";
              if (status) {
                status.innerHTML = `<span style="color:#ef4444;">❌ ล้มเหลว: ${errMsg}</span>`;
              }
              resolve({
                success: false,
                platform: "Shopee",
                message: errMsg
              });
            }
          }
        );
      });
    });
  }

  // 4. Listen for background & popup auto-sync signals (ASYNC WITH return true!)
  if (chrome && chrome.runtime && chrome.runtime.onMessage) {
    chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
      if (request.action === "START_AUTO_SYNC") {
        console.log("DDCar: Received START_AUTO_SYNC from background", request.source);
        performSync(request.source)
          .then((res) => sendResponse(res))
          .catch((err) => sendResponse({ success: false, platform: "Shopee", message: err.message }));
        return true;
      } else if (request.action === "NAVIGATE_TO_ORDERS") {
        window.location.href = "https://seller.shopee.co.th/portal/sale/order";
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
