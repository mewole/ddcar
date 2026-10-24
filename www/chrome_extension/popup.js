document.addEventListener("DOMContentLoaded", () => {
  const selChannel = document.getElementById("sel-channel");
  const txtServer = document.getElementById("txt-server");
  const btnSyncNow = document.getElementById("btn-sync-now");
  const btnSyncCatalog = document.getElementById("btn-sync-catalog");
  const btnGotoOrders = document.getElementById("btn-goto-orders");
  const statusText = document.getElementById("status-text");
  const btnOpenDDCar = document.getElementById("btn-open-ddcar");
  const activePlatformName = document.getElementById("active-platform-name");

  let currentTabInfo = null;

  // 1. Detect active tab platform
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    const activeTab = tabs && tabs[0];
    if (activeTab && activeTab.url) {
      const url = activeTab.url.toLowerCase();

      if (url.includes("sellercenter.lazada.co.th") || url.includes("lazada.co.th")) {
        currentTabInfo = {
          id: activeTab.id,
          platform: "Lazada",
          code: "lazada_main",
          script: "content_lazada.js",
          orderUrl: "https://sellercenter.lazada.co.th/order/query",
          catalogUrl: "https://sellercenter.lazada.co.th/apps/product/list"
        };
        activePlatformName.innerHTML = `<span style="color:#0284c7;">🔵 Lazada</span>`;
        selChannel.value = "lazada_main";
        showStatus("พร้อมดึงข้อมูลจาก Lazada", "#0284c7");
      } else if (url.includes("seller-th.tiktok.com") || url.includes("seller.tiktok.com")) {
        currentTabInfo = {
          id: activeTab.id,
          platform: "TikTok Shop",
          code: "tiktok_main",
          script: "content_tiktok.js",
          orderUrl: "https://seller-th.tiktok.com/order",
          catalogUrl: "https://seller-th.tiktok.com/product/manage"
        };
        activePlatformName.innerHTML = `<span style="color:#e11d48;">⚫ TikTok Shop</span>`;
        selChannel.value = "tiktok_main";
        showStatus("พร้อมดึงข้อมูลจาก TikTok Shop", "#e11d48");
      } else if (url.includes("seller.shopee.co.th")) {
        currentTabInfo = {
          id: activeTab.id,
          platform: "Shopee",
          code: "shopee_kingkong",
          script: "content_shopee.js",
          orderUrl: "https://seller.shopee.co.th/portal/sale/order",
          catalogUrl: "https://seller.shopee.co.th/portal/product/list/all"
        };
        activePlatformName.innerHTML = `<span style="color:#ea580c;">🟠 Shopee</span>`;
        showStatus("พร้อมดึงข้อมูลจาก Shopee", "#ea580c");
      } else {
        activePlatformName.textContent = "ไม่ได้เปิดหน้าร้านค้า";
        showStatus("กรุณาเปิดแท็บ Shopee, Lazada หรือ TikTok", "#64748b");
      }
    }

    // Load saved preferences
    chrome.storage.local.get(["channelCode", "serverUrl"], (data) => {
      if (data.channelCode && !currentTabInfo) {
        selChannel.value = data.channelCode;
      }
      if (data.serverUrl) {
        txtServer.value = data.serverUrl;
        btnOpenDDCar.href = data.serverUrl.replace(/\/+$/, "") + "/web_platform_mapping.html";
      }
    });
  });

  // Save on change
  selChannel.addEventListener("change", () => {
    chrome.storage.local.set({ channelCode: selChannel.value }, () => {
      showStatus("✅ บันทึกร้านค้านี้แล้ว", "#15803d");
    });
  });

  txtServer.addEventListener("change", () => {
    let url = txtServer.value.trim();
    if (!url.endsWith("/")) url += "/";
    chrome.storage.local.set({ serverUrl: url }, () => {
      btnOpenDDCar.href = url + "web_platform_mapping.html";
      showStatus("✅ บันทึกที่อยู่เซิร์ฟเวอร์แล้ว", "#15803d");
    });
  });

  // Goto Orders Action
  btnGotoOrders.addEventListener("click", () => {
    if (currentTabInfo && currentTabInfo.orderUrl) {
      chrome.tabs.update(currentTabInfo.id, { url: currentTabInfo.orderUrl }, () => {
        showStatus("กำลังเปลี่ยนหน้าไปยัง 'จัดการคำสั่งซื้อ'...", "#2563eb");
        btnGotoOrders.style.display = "none";
        setTimeout(() => {
          window.close();
        }, 1200);
      });
    }
  });

  // 2. Trigger Sync Now (ปิดชั่วคราวตามมติเจ้าของร้านและนักวิเคราะห์ จนกว่า Phase 3 จะเสร็จ)
  btnSyncNow.addEventListener("click", () => {
    showStatus("⚠️ ปิดรับคำสั่งซื้อชั่วคราวเพื่อเตรียมอัปเกรด Phase 3 ป้องกันข้อมูลซ้ำ", "#d97706");
    return;

      // Find open tab based on selected dropdown
      const selectedChannel = selChannel.value;
      let targetPattern = "*://seller.shopee.co.th/*";
      let pName = "Shopee";
      let script = "content_shopee.js";
      let ordUrl = "https://seller.shopee.co.th/portal/sale/order";

      if (selectedChannel === "lazada_main") {
        targetPattern = "*://sellercenter.lazada.co.th/*";
        pName = "Lazada";
        script = "content_lazada.js";
        ordUrl = "https://sellercenter.lazada.co.th/order/query";
      } else if (selectedChannel === "tiktok_main") {
        targetPattern = "*://seller-th.tiktok.com/*";
        pName = "TikTok Shop";
        script = "content_tiktok.js";
        ordUrl = "https://seller-th.tiktok.com/order";
      }

      chrome.tabs.query({ url: targetPattern }, (foundTabs) => {
        if (foundTabs && foundTabs.length > 0) {
          executeSyncOnTab({
            id: foundTabs[0].id,
            platform: pName,
            script: script,
            orderUrl: ordUrl
          });
        } else {
          showStatus(`⚠️ กรุณาเปิดแท็บ ${pName} ในเบราว์เซอร์ก่อน`, "#d97706");
        }
      });
      return;
    }

    executeSyncOnTab(currentTabInfo);
  });

  // 3. Trigger Catalog Sync
  btnSyncCatalog.addEventListener("click", () => {
    btnGotoOrders.style.display = "none";

    if (!currentTabInfo) {
      const selectedChannel = selChannel.value;
      let targetPattern = "*://seller.shopee.co.th/*";
      let pName = "Shopee";
      let script = "content_shopee.js";
      let catUrl = "https://seller.shopee.co.th/portal/product/list/all";

      if (selectedChannel === "lazada_main") {
        targetPattern = "*://sellercenter.lazada.co.th/*";
        pName = "Lazada";
        script = "content_lazada.js";
        catUrl = "https://sellercenter.lazada.co.th/apps/product/list";
      } else if (selectedChannel === "tiktok_main") {
        targetPattern = "*://seller-th.tiktok.com/*";
        pName = "TikTok Shop";
        script = "content_tiktok.js";
        catUrl = "https://seller-th.tiktok.com/product/manage";
      }

      chrome.tabs.query({ url: targetPattern }, (foundTabs) => {
        if (foundTabs && foundTabs.length > 0) {
          executeCatalogSyncOnTab({
            id: foundTabs[0].id,
            platform: pName,
            script: script,
            catalogUrl: catUrl
          });
        } else {
          showStatus(`⚠️ กรุณาเปิดแท็บ ${pName} ก่อนซิงค์แคตตาล็อก`, "#d97706");
        }
      });
      return;
    }

    executeCatalogSyncOnTab(currentTabInfo);
  });

  function executeCatalogSyncOnTab(tabInfo) {
    btnSyncCatalog.disabled = true;
    showStatus(`⏳ กำลังดึงแคตตาล็อกสินค้าจาก ${tabInfo.platform}...`, "#0284c7");

    chrome.tabs.sendMessage(tabInfo.id, { action: "START_CATALOG_SYNC", source: "popup_button" }, (response) => {
      btnSyncCatalog.disabled = false;
      if (chrome.runtime.lastError) {
        showStatus(`⚠️ กรุณารีเฟรช (F5) หน้า ${tabInfo.platform} ก่อนซิงค์แคตตาล็อก`, "#d97706");
      } else if (response && response.success) {
        showStatus(`✅ ${response.message || "ซิงค์แคตตาล็อกสำเร็จ!"}`, "#15803d");
      } else {
        showStatus(`⚠️ ${response && response.message ? response.message : "ไม่พบตารางสินค้า"}`, "#b45309");
        if (tabInfo.catalogUrl) {
          btnGotoOrders.style.display = "block";
          btnGotoOrders.textContent = `👉 ไปที่หน้าจัดการสินค้า (Product List) ${tabInfo.platform}`;
          btnGotoOrders.onclick = () => {
            chrome.tabs.update(tabInfo.id, { url: tabInfo.catalogUrl }, () => window.close());
          };
        }
      }
    });
  }

  function executeSyncOnTab(tabInfo) {
    currentTabInfo = tabInfo;
    btnSyncNow.disabled = true;
    showStatus(`⏳ กำลังดึงข้อมูลจาก ${tabInfo.platform}...`, "#2563eb");

    // Try messaging directly
    chrome.tabs.sendMessage(tabInfo.id, { action: "START_AUTO_SYNC", source: "popup_button" }, (response) => {
      if (chrome.runtime.lastError) {
        // If content script wasn't loaded (e.g. page wasn't refreshed), dynamically inject it now!
        console.warn("Content script not responding, injecting dynamically:", tabInfo.script);
        showStatus("⚡ กำลังเชื่อมต่อเข้าหน้าเว็บ...", "#2563eb");

        if (chrome.scripting && chrome.scripting.executeScript) {
          chrome.scripting.executeScript(
            {
              target: { tabId: tabInfo.id },
              files: [tabInfo.script]
            },
            () => {
              // Retry sending message after injection
              setTimeout(() => {
                chrome.tabs.sendMessage(tabInfo.id, { action: "START_AUTO_SYNC", source: "popup_button" }, (retryRes) => {
                  if (chrome.runtime.lastError) {
                    console.log("Retry message handled:", chrome.runtime.lastError.message);
                  }
                  btnSyncNow.disabled = false;
                  handleSyncResponse(retryRes, tabInfo);
                });
              }, 500);
            }
          );
        } else {
          btnSyncNow.disabled = false;
          showStatus(`⚠️ กรุณากดรีเฟรช (F5) หน้า ${tabInfo.platform} 1 ครั้งก่อน`, "#d97706");
        }
      } else {
        btnSyncNow.disabled = false;
        handleSyncResponse(response, tabInfo);
      }
    });
  }

  function handleSyncResponse(response, tabInfo) {
    if (!response) {
      showStatus(`⚠️ ไม่ได้รับการตอบกลับจาก ${tabInfo.platform} (กรุณารีเฟรช F5 หน้าร้านค้า)`, "#d97706");
      return;
    }

    if (response.isDashboard) {
      showStatus(`⚠️ ขณะนี้อยู่ที่หน้าภาพรวมร้านค้า (ไม่ใช่หน้าคำสั่งซื้อ)`, "#b45309");
      btnGotoOrders.style.display = "block";
      btnGotoOrders.textContent = `👉 คลิกเพื่อเปิดหน้า 'จัดการคำสั่งซื้อ' ${tabInfo.platform}`;
      return;
    }

    if (response.success) {
      showStatus(`✅ ${response.message || "ซิงค์สำเร็จเรียบร้อย!"}`, "#15803d");
      btnGotoOrders.style.display = "none";
    } else {
      showStatus(`⚠️ ${response.message || "ยังไม่พบรายการออเดอร์ในตารางหน้านี้"}`, "#b45309");
      if (tabInfo.orderUrl) {
        btnGotoOrders.style.display = "block";
        btnGotoOrders.textContent = `👉 ไปที่หน้า 'จัดการคำสั่งซื้อ' ${tabInfo.platform}`;
      }
    }
  }

  function showStatus(msg, color) {
    statusText.innerHTML = msg;
    statusText.style.color = color || "#475569";
  }
});
