// DDCar Sync Assistant - Background Service Worker
// Manages scheduled alarms (Morning & 5:00 PM Evening) and API communication

const DEFAULT_SERVER_URL = "http://100.77.57.16:8083/dd-shop/";

// Initialize extension settings
chrome.runtime.onInstalled.addListener(() => {
  console.log("DDCar Sync Assistant Installed");
  chrome.storage.local.get(["serverUrl", "channelCode", "morningHour", "eveningHour"], (res) => {
    if (!res.serverUrl) {
      chrome.storage.local.set({ serverUrl: DEFAULT_SERVER_URL });
    }
    if (!res.channelCode) {
      chrome.storage.local.set({ channelCode: "shopee_kingkong" });
    }
    setupAlarms();
  });
});

// Setup Scheduled Alarms (Morning & 17:00 Evening)
function setupAlarms() {
  chrome.alarms.clearAll(() => {
    // Schedule check every 15 minutes to test for 17:00 ICT and morning
    chrome.alarms.create("check_schedule_alarm", { periodInMinutes: 15 });
    console.log("DDCar Sync Alarms initialized");
  });
}

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === "check_schedule_alarm") {
    checkAndTriggerSchedule();
  }
});

function checkAndTriggerSchedule() {
  const now = new Date();
  const currentHour = now.getHours();
  const currentMinute = now.getMinutes();

  chrome.storage.local.get(["lastMorningSync", "lastEveningSync"], (data) => {
    const todayStr = now.toISOString().split("T")[0];

    // Morning Sync: Between 08:30 and 11:00 (once per day)
    if (currentHour >= 8 && currentHour < 11 && data.lastMorningSync !== todayStr) {
      console.log("Triggering Morning Auto-Sync for", todayStr);
      chrome.storage.local.set({ lastMorningSync: todayStr });
      triggerActiveTabSync("morning_auto");
    }

    // Evening Sync: At 17:00 (5 PM ICT) between 17:00 and 18:30 (once per day)
    if (currentHour >= 17 && currentHour < 19 && data.lastEveningSync !== todayStr) {
      console.log("Triggering Evening 5:00 PM Auto-Sync for", todayStr);
      chrome.storage.local.set({ lastEveningSync: todayStr });
      triggerActiveTabSync("evening_5pm_auto");
    }
  });
}

function triggerActiveTabSync(source) {
  const sellerPatterns = [
    "*://seller.shopee.co.th/*",
    "*://sellercenter.lazada.co.th/*",
    "*://seller-th.tiktok.com/*"
  ];

  sellerPatterns.forEach((pattern) => {
    chrome.tabs.query({ url: pattern }, (tabs) => {
      if (tabs && tabs.length > 0) {
        tabs.forEach((tab) => {
          chrome.tabs.sendMessage(tab.id, { action: "START_AUTO_SYNC", source: source }, (response) => {
            if (chrome.runtime.lastError) {
              // Tab is not ready or not on active seller order page yet - ignore silently
              return;
            }
            console.log("Sync Response from:", pattern, response);
          });
        });
      }
    });
  });
}

// Listen for messages from content scripts or popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === "SEND_ORDERS_TO_DDCAR") {
    sendOrdersToDDCar(request.payload)
      .then((res) => sendResponse({ success: true, data: res }))
      .catch((err) => sendResponse({ success: false, error: err.toString() }));
    return true; // async
  } else if (request.action === "SEND_CATALOG_TO_DDCAR") {
    sendCatalogToDDCar(request.payload)
      .then((res) => sendResponse({ success: true, data: res }))
      .catch((err) => sendResponse({ success: false, error: err.toString() }));
    return true; // async
  }
});

async function sendOrdersToDDCar(payload) {
  const storage = await chrome.storage.local.get(["serverUrl", "channelCode"]);
  let baseUrl = storage.serverUrl || DEFAULT_SERVER_URL;
  if (!baseUrl.endsWith("/")) baseUrl += "/";
  const targetUrl = baseUrl + "php/sync_platform_orders.php";

  const channelCode = payload.channel_code || storage.channelCode || "shopee_kingkong";
  payload.channel_code = channelCode;

  console.log("Posting orders to DDCar:", targetUrl, payload);

  const response = await fetch(targetUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
  }

  const result = await response.json();
  return result;
}

async function sendCatalogToDDCar(payload) {
  const storage = await chrome.storage.local.get(["serverUrl", "channelCode"]);
  let baseUrl = storage.serverUrl || DEFAULT_SERVER_URL;
  if (!baseUrl.endsWith("/")) baseUrl += "/";
  const targetUrl = baseUrl + "php/sync_platform_catalog.php";

  const channelCode = payload.channel_code || storage.channelCode || "shopee_kingkong";
  payload.channel_code = channelCode;

  console.log("Posting catalog to DDCar:", targetUrl, payload);

  const response = await fetch(targetUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
  }

  const result = await response.json();
  return result;
}
