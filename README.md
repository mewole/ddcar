# 🚗 DDCAR - ระบบบริหารจัดการสต็อก จุดขาย (POS) และระบบลูกหนี้/เครดิต
> **DD MOTOR SPORT (DD Accessory)**  
> ระบบ Point of Sale (POS), Inventory & Multi-Warehouse Management (WMS), การออกบิล/ใบวางบิล และระบบควบคุมสต็อกสินค้า สำหรับธุรกิจจัดจำหน่ายอะไหล่และอุปกรณ์ตกแต่งรถยนต์ ทั้งในรูปแบบแอปพลิเคชันมือถือ (Android) และเว็บแอปพลิเคชันสำหรับคอมพิวเตอร์หน้าร้าน

---

## 📋 สารบัญ
- [1. ภาพรวมของระบบ (Project Overview)](#1-ภาพรวมของระบบ-project-overview)
- [2. สถาปัตยกรรมและเทคโนโลยี (Tech Stack)](#2-สถาปัตยกรรมและเทคโนโลยี-tech-stack)
- [3. ฟังก์ชันการทำงานหลัก (Core Features)](#3-ฟังก์ชันการทำงานหลัก-core-features)
- [4. โครงสร้างโฟลเดอร์ในโปรเจกต์ (Project Structure)](#4-โครงสร้างโฟลเดอร์ในโปรเจกต์-project-structure)
- [5. โครงสร้างฐานข้อมูล (Database Schema)](#5-โครงสร้างฐานข้อมูล-database-schema)
- [6. สิทธิ์และการเข้าถึงของผู้ใช้งาน (User Roles & Permissions)](#6-สิทธิ์และการเข้าถึงของผู้ใช้งาน-user-roles--permissions)
- [7. ขั้นตอนการติดตั้งและเริ่มต้นใช้งาน (Installation & Setup)](#7-ขั้นตอนการติดตั้งและเริ่มต้นใช้งาน-installation--setup)
- [8. ข้อสังเกตและแนวทางพัฒนาต่อยอด (Security & Performance Guidelines)](#8-ข้อสังเกตและแนวทางพัฒนาต่อยอด-security--performance-guidelines)

---

## 1. ภาพรวมของระบบ (Project Overview)

**DDCAR** ถูกออกแบบมาเพื่อตอบโจทย์การบริหารงานขายและคลังสินค้าอะไหล่ยนต์ที่มีความซับซ้อน รองรับ:
1. **คลังสินค้าหลายสาขา**: ทั้งคลังสินค้าใหญ่ (โกดัง) และสาขาหน้าร้าน (Shop Front)
2. **การระบุตำแหน่งจัดเก็บละเอียด**: ระบุล็อก/ชั้นวาง (Shelf / Bin Location) ของแต่ละสินค้าในแต่ละคลัง
3. **โครงสร้างหมวดหมู่แบบต้นไม้หลายชั้น (Hierarchical Categories)**: เช่น ยี่ห้อรถยนต์ ➔ รุ่นรถยนต์ ➔ ชิ้นส่วนอะไหล่ ➔ รหัสสินค้า
4. **ราคาเฉพาะลูกค้ารายบุคคล (Customer Tier Pricing)**: ระบบจดจำราคาที่เคยตกลงซื้อขายกับลูกค้ารายนั้นๆ โดยอัตโนมัติ
5. **การสแกนบาร์โค้ดและคิวอาร์โค้ด**: รองรับทั้งกล้องมือถือผ่าน Capacitor Barcode Scanner และเครื่องยิงบาร์โค้ด USB บนคอมพิวเตอร์
6. **ระบบลูกหนี้ เครดิต และใบวางบิล**: สรุปยอดค้างชำระ ตัดรอบสิ้นเดือน และพิมพ์ใบวางบิลมาตรฐาน A4

---

## 2. สถาปัตยกรรมและเทคโนโลยี (Tech Stack)

```
+-----------------------------------------------------------------------------------+
|                                  DDCAR PLATFORM                                   |
+-----------------------------------------------------------------------------------+
|  [ Mobile App (Android/iOS) ]        |  [ Web Admin / Desktop POS ]               |
|  - Capacitor 8 Hybrid Framework      |  - Single Page Style (Bootstrap 4, jQuery) |
|  - Camera Barcode Scanner Plugin     |  - Hardware USB Barcode Scanner Detection  |
|  - Offline-ready Assets (www/)       |  - A4 & ใบวางบิล Printing Templates        |
+--------------------------------------+--------------------------------------------+
                                  │ (HTTP / AJAX)
                                  ▼
+-----------------------------------------------------------------------------------+
|                        API & Backend Layer (PHP 7/8)                              |
|  - Location: www/php/ (97 Endpoints)                                              |
|  - Hosted at: http://ddaccessory.trueddns.com:24330/dd-shop/                      |
|  - Transaction Management (mysqli_begin_transaction)                              |
|  - MPTT Tree Algorithm for Nested Categories                                      |
+-----------------------------------------------------------------------------------+
                                  │ (mysqli)
                                  ▼
+-----------------------------------------------------------------------------------+
|                              Database (MySQL)                                     |
|  - Database Name: ddcar                                                           |
+-----------------------------------------------------------------------------------+
```

### รายละเอียดเทคโนโลยี
* **Mobile Framework**: [Capacitor 8](https://capacitorjs.com/) (`@capacitor/core: ^8.4.2`, `@capacitor/android: ^8.4.2`, `@capacitor/barcode-scanner: ^3.1.0`)
* **Android Target**: `compileSdkVersion 36`, `targetSdkVersion 36`, `minSdkVersion 26` (รองรับ Android 8.0 จนถึง Android 14–16)
* **Frontend UI Library**: HTML5, Vanilla CSS3, Bootstrap 4, FontAwesome 5, jQuery 3.3.1, jQuery-Confirm
* **Frontend Utilities**:
  * `onscan.js` / `jquery.scannerdetection.js`: ตรวจจับคีย์สโตรกจากเครื่องสแกนบาร์โค้ด USB แบบฮาร์ดแวร์
  * `jquery.easy-autocomplete.js`: ระบบค้นหาสินค้าและลูกค้าแบบพิมพ์ค้นหาอัตโนมัติ
  * `qrcode.js`: เจเนอเรต QR Code แบบไดนามิกสำหรับติดฉลากสินค้าและเอกสารโอนย้าย
  * `datepicker.js` / `datepicker.th-TH.js`: ระบบเลือกวันที่ภาษาไทย
* **Backend API**: PHP สไตล์ Procedural Scripting มี Transaction Handling (`mysqli_begin_transaction`, `mysqli_commit`, `mysqli_rollback`)
* **Database**: MySQL (`ddcar`) รองรับ UTF-8 ภาษาไทยสมบูรณ์

---

## 3. ฟังก์ชันการทำงานหลัก (Core Features)

### 3.1 ระบบงานขายและออกบิล (Point of Sale & Invoicing)
* **เลือกขายได้จากทั้งหน้าร้านและโกดัง**: ตัดยอดสต็อกตามสาขาที่เลือกโดยตรง
* **Dynamic Customer Pricing**: บันทึกและจดจำราคาขายล่าสุดของลูกค้าแต่ละคนในตาราง `detail_cus` เมื่อลูกค้าเดิมซื้อสินค้ารายการเดิม ระบบจะดึงราคาพิเศษที่เคยซื้อให้อัตโนมัติ
* **รูปแบบการชำระเงิน**: รองรับทั้งเงินสด, โอนเงิน และเครดิต (ผูกเข้าสู่ระบบลูกหนี้)
* **รหัสเอกสารการขายอัตโนมัติ**: รูปแบบ `S{YY}-{Sequence 5 หลัก}` (เช่น `S24-00001`)
* **การยกเลิกบิล (Rollback)**: เมื่อยกเลิกบิล ระบบจะคืนสต็อกสินค้าเข้าคลังเดิมอัตโนมัติ, หักยอดเครดิตคืน, เติม `(cancel)` ต่อท้ายเลขบิล และบันทึกประวัติการยกเลิกลงตาราง `sale_order_remove` พร้อมระบุเหตุผลและผู้ทำรายการ

### 3.2 ระบบจัดการคลังสินค้าและสต็อก (Multi-Warehouse & Stock Management)
* **แยกพื้นที่จัดเก็บ**: รองรับโกดังสินค้าหลายแห่ง (`warehouse`) และหน้าร้านหลายสาขา (`shop`)
* **ระบบระบุชั้นวางสินค้า (Bin Location)**: ระบุตำแหน่งล็อก/ชั้นวางของสินค้าได้ เช่น ชั้น A1, ล็อก 2 (`warehouse_place`, `shop_place`)
* **การนำสินค้าเข้า (Stock In)**: รหัสเอกสาร `IMP{YY}-{Sequence}` บันทึกต้นทุนสินค้าและสถานที่จัดเก็บ
* **การเบิกสินค้าออก (Stock Out / Export)**: รหัสเอกสาร `EXP{YY}-{Sequence}` สำหรับเบิกสินค้าออกจากโกดัง
* **การโอนย้ายสินค้าระหว่างคลัง (Inter-warehouse Transfer)**: โอนย้ายสินค้าระหว่างหน้าร้านและโกดัง หรือโอนระหว่างสาขา
* **ระบบแจ้งเตือนสต็อกสินค้า (Threshold & Alerts)**:
  * สถานะ `normal`: จำนวนสินค้าเพียงพอ
  * สถานะ `warning`: จำนวนสินค้าต่ำกว่าหรือเท่ากับจุดสั่งซื้อ (`min_amount`)
  * สถานะ `danger`: จำนวนสินค้าเหลือน้อยวิกฤต (น้อยกว่าหรือเท่ากับ 10 ชิ้น)
  * แจ้งเตือนผ่านไอคอนกระดิ่งพร้อมไฟสีแดง (`.dot`) ทั้งบน Mobile และ Web

### 3.3 ระบบโครงสร้างหมวดหมู่แบบต้นไม้ (MPTT Tree Hierarchy)
* ใช้ขั้นตอนวิธี **Modified Preorder Tree Traversal (MPTT)** ผ่านตาราง `tree` โดยมีฟิลด์ `lft`, `rgt`, `title`, `parent_title`
* เริ่มต้นจากโหนดรูท `'car'` สามารถสร้างโฟลเดอร์ย่อยได้ไม่จำกัดชั้น:
  ```
  car (Root)
  └── Toyota
      └── Fortuner
          └── กระจังหน้า
              └── [รหัสสินค้า: 1052] กระจังหน้า Fortuner ทรง TRD
  ```

### 3.4 ระบบลูกหนี้ เครดิต และใบวางบิล (Credit & Billing Statements)
* สรุปยอดค้างชำระรายเดือนของลูกค้าแต่ละราย
* แจ้งเตือนเมื่อถึงวันสิ้นเดือนให้ตรวจสอบและตัดรอบบิลเครดิต
* **การพิมพ์ใบวางบิล (`ใบวางบิล.html`)**: ฟอร์แมตขนาดกระดาษ A4 แนวนอน (297mm x 210mm) พร้อมคำนวณยอดรวม ยอดชำระแล้ว ยอดคงค้าง และตัดหน้าอัตโนมัติเมื่อข้อมูลยาวเกิน 1 หน้า (`page-break-after: always`)

### 3.5 ระบบสแกนและพิมพ์ Barcode / QR Code
* **Mobile Camera Scan**: สแกนค้นหาสินค้า ดูสต็อกในทุกโกดัง ราคาปลีก/ส่ง/ทุน และตำแหน่งชั้นวาง
* **Batch Transfer QR**: รองรับการสแกน QR Code สำหรับรับสินค้าเข้าสต็อกแบบชุด `[transfer]prod_id_list|cost_list|amt_list`
* **USB Hardware Scanner**: รองรับหัวอ่านบาร์โค้ด USB ที่ต่อกับเครื่องคอมพิวเตอร์ ยิงบาร์โค้ดสินค้าเข้าบิลขายได้ทันที
* **การพิมพ์ Barcode/QR Label**: ระบบสร้าง QR Code สำหรับพิมพ์สติกเกอร์แปะหน้ากล่องสินค้า

### 3.6 ระบบรายงานและสรุปยอด (Reports & Analytics)
* **รายงานสรุปยอดขาย**: สรุปยอดขายรวมและกำไรสุทธิ แยกตามวัน, เดือน หรือปี
* **รายงานสินค้าเข้า-ออก**: ดูประวัติการนำเข้าและเบิกออกย้อนหลัง
* **รายงานมูลค่าสต็อกรวม**: คำนวณมูลค่าสินค้าคงคลังทั้งหมด (Total Stock Value) ตามราคาต้นทุน

---

## 4. โครงสร้างโฟลเดอร์ในโปรเจกต์ (Project Structure)

```
ddcar/
├── android/                   # โครงการ Native Android (Gradle / Capacitor Android)
│   ├── app/                   # ซอร์สโค้ด Android (MainActivity, Manifest, build.gradle)
│   └── variables.gradle       # กำหนดเวอร์ชัน SDK (Target SDK 36, Compile SDK 36)
├── ipa/                       # ไฟล์แพ็กเกจเดิมของ iOS (ddcar_old.ipa)
├── plugins/                   # ปลั๊กอิน Cordova เสริมที่เชื่อมโยงกับ Capacitor
├── res/                       # ไอคอนและ Splash Screen สำหรับ Android/iOS
├── www/                       # Web Application Assets ทั้งหมด (HTML, CSS, JS, PHP)
│   ├── index.html             # หน้าแรกสำหรับ Mobile App
│   ├── login.html             # หน้าเข้าสู่ระบบ (Mobile)
│   ├── sale.html              # หน้าขายสินค้า (Mobile)
│   ├── stock.html             # หน้าตรวจสอบสต็อกสินค้า (Mobile)
│   ├── import.html            # หน้ารับสินค้าเข้า (Mobile)
│   ├── export.html            # หน้าเบิกสินค้าออก (Mobile)
│   ├── customer.html          # หน้าจัดการข้อมูลลูกค้า (Mobile)
│   ├── warehouse.html         # หน้าจัดการโกดัง/หน้าร้าน (Mobile)
│   ├── web_login.html         # หน้าเข้าสู่ระบบ (Desktop Web)
│   ├── web_sale.html          # หน้าขายสินค้า POS (Desktop Web)
│   ├── web_stock.html         # หน้าเช็คสต็อกและจัดการสินค้า (Desktop Web)
│   ├── web_import.html        # หน้านำสินค้าเข้า (Desktop Web)
│   ├── web_export.html        # หน้าเบิกสินค้าออก (Desktop Web)
│   ├── web_mng_bill.html      # หน้ารายงานและจัดการบิลขาย (Desktop Web)
│   ├── web_mng_sale.html      # หน้ารายงานสรุปยอดขายและกำไร (Desktop Web)
│   ├── web_credit.html        # หน้ารายงานลูกหนี้และเครดิต (Desktop Web)
│   ├── web_qrprint.html       # หน้าพิมพ์คิวอาร์โค้ดติดสินค้า
│   ├── a4.html                # เทมเพลตใบรายการขายขนาด A4
│   ├── ใบวางบิล.html           # เทมเพลตใบวางบิลขนาด A4 แนวนอน
│   ├── css/                   # สไตล์ชีท (index.css, untitled.css, datepicker.css ฯลฯ)
│   ├── js/                    # สคริปต์ส่วนกลาง (config.js, scanqr.js, onscan.js ฯลฯ)
│   └── php/                   # Backend API Scripts (จำนวน 97 ไฟล์)
│       ├── db.php             # ไฟล์เชื่อมต่อฐานข้อมูล MySQL
│       ├── login.php          # ตรวจสอบการเข้าสู่ระบบ (password_verify)
│       ├── save_order.php     # บันทึกการขาย ตัดสต็อก บันทึกเครดิต
│       ├── delete_bill.php    # ยกเลิกบิลและคืนสต็อก (Rollback)
│       ├── stock_in.php       # รับสินค้าเข้าคลัง
│       ├── stock_out.php      # เบิกสินค้าออกจากคลัง
│       ├── get_folder.php     # ดึงโครงสร้างหมวดหมู่แบบต้นไม้
│       ├── add_prod.php       # เพิ่มสินค้าใหม่ลงในตาราง product และ tree
│       └── ...                # สคริปต์ CRUD ข้อมูลอื่นๆ
├── capacitor.config.json      # การตั้งค่า Capacitor Framework
├── config.xml                 # การตั้งค่าเดิมของ Apache Cordova
└── package.json               # รายการ Dependencies และสคริปต์ Node.js
```

---

## 5. โครงสร้างฐานข้อมูล (Database Schema)

ตารางหลักในฐานข้อมูล `ddcar` ประกอบด้วย:

| ชื่อตาราง | คำอธิบาย | ฟิลด์สำคัญ |
| :--- | :--- | :--- |
| `user` | ข้อมูลผู้ใช้งานระบบ | `user_id`, `user_name`, `pass` (Hash), `phone`, `user_role` |
| `product` | ข้อมูลสินค้าหลัก | `prod_id`, `prod_code`, `prod_name`, `prod_cost`, `prod_pricesend`, `prod_price`, `min_amount`, `status`, `img` |
| `tree` | โครงสร้างหมวดหมู่แบบ MPTT | `title`, `lft`, `rgt`, `parent_title` |
| `warehouse_detail` | รายชื่อโกดังสินค้า | `ware_id`, `ware_name` |
| `shop_detail` | รายชื่อสาขาหน้าร้าน | `shop_id`, `shop_name` |
| `warehouse` | จำนวนสต็อกสินค้าในแต่ละโกดัง | `ware_id`, `prod_id`, `amount` |
| `shop` | จำนวนสต็อกสินค้าในแต่ละหน้าร้าน | `shop_id`, `prod_id`, `amount` |
| `warehouse_place` | ตำแหน่งชั้นวางในโกดัง | `ware_id`, `prod_id`, `place` |
| `shop_place` | ตำแหน่งชั้นวางในหน้าร้าน | `shop_id`, `prod_id`, `place` |
| `customer` | ข้อมูลลูกค้า | `cus_id`, `cus_name`, `cus_tel`, `cus_addr`, `cus_type` (ปลีก/ส่ง) |
| `detail_cus` | ราคาสินค้าพิเศษเฉพาะลูกค้าแต่ละราย | `cus_id`, `prod_id`, `cus_price` |
| `sale_order` | บิลขายสินค้า | `order_id`, `order_number`, `customer_id`, `date_time`, `total_price`, `payment_type`, `user_id`, `branch_id`, `type`, `credit_id` |
| `sale_order_item` | รายการสินค้าในบิลขาย | `order_id`, `item_id`, `prod_id`, `prod_price`, `prod_amount` |
| `sale_order_remove` | ประวัติการยกเลิกบิลขาย | `order_id`, `user_id`, `date_time`, `remark` |
| `stock_in` / `item` | ข้อมูลการรับสินค้าเข้า | `stock_id`, `stock_number`, `ware_id`, `date_time`, `total_stock` |
| `stock_out` / `item`| ข้อมูลการเบิกสินค้าออก | `stock_out_id`, `stock_out_number`, `ware_id`, `date_time` |
| `credit` | ยอดลูกหนี้เครดิตประจำเดือน | `credit_id`, `credit_num`, `cus_id`, `credit_price`, `date_time` |
| `bell` | สถานะแจ้งเตือนสต็อกรวม | `status` (`normal` / `alert`) |
| `version` | ตรวจสอบการอัปเดตเวอร์ชันของแอป | `version`, `url` |

---

## 6. สิทธิ์และการเข้าถึงของผู้ใช้งาน (User Roles & Permissions)

ระบบแบ่งระดับสิทธิ์ของผู้ใช้งานออกเป็น 4 ระดับ:

| ระดับสิทธิ์ | สิทธิ์การเข้าถึงและการมองเห็น |
| :--- | :--- |
| **`เจ้าของร้าน`** | **เข้าถึงได้ครบทุกฟังก์ชัน**: ดูราคาต้นทุน, ดูรายงานสรุปยอดขาย, ดูกำไรสุทธิ, จัดการสมาชิก, เพิ่ม/ลบสินค้า และแก้ไขบิล |
| **`แอดมิน`** | **จัดการระบบ**: จัดการข้อมูลสินค้า, หมวดหมู่, สมาชิกผู้ใช้งาน, จัดการหน้าร้าน/โกดัง และรับเข้า-เบิกออกสินค้า |
| **`พนักงาน`** | **ปฏิบัติการขายหน้าร้าน**: ขายสินค้า, สแกนสินค้า, ตรวจสอบสต็อก (แต่ระบบจะ**ซ่อนราคาต้นทุน** แสดงเฉพาะราคาส่ง/ปลีก และ**ซ่อนรายงานสรุปยอดขาย/กำไร**) |
| **`ดูคลังสินค้า`** | **ตรวจนับสต็อกเท่านั้น**: ดูข้อมูลสต็อกและตำแหน่งจัดเก็บสินค้าได้เพียงอย่างเดียว (ปุ่มทำรายการขาย, รับเข้า, เบิกออก จะถูกปิดการใช้งาน) |

---

## 7. ขั้นตอนการติดตั้งและเริ่มต้นใช้งาน (Installation & Setup)

### 7.1 ข้อกำหนดเบื้องต้น (Prerequisites)
* **Node.js** (เวอร์ชัน 18.x หรือ 20.x ขึ้นไป) และ **npm**
* **PHP** (เวอร์ชัน 7.4 หรือ 8.x) พร้อมโมดูล `mysqli` และ `json`
* **MySQL Server** (หรือ MariaDB)
* **Android Studio** พร้อม Android SDK Platform 34-36 และ JDK 17+ (สำหรับการ Build Mobile App)

### 7.2 การตั้งค่าฐานข้อมูล (Database Setup)
1. ติดตั้งและเปิดบริการ MySQL Server
2. สร้างฐานข้อมูลชื่อ `ddcar`:
   ```sql
   CREATE DATABASE ddcar CHARACTER SET utf8 COLLATE utf8_general_ci;
   ```
3. นำเข้าตารางและโครงสร้างเริ่มต้น
4. แก้ไขไฟล์คอนฟิกฐานข้อมูลที่ [www/php/db.php](file:///d:/MewP/ddcar/www/php/db.php):
   ```php
   <?php
       header("Access-Control-Allow-Origin: *");
       $conn = mysqli_connect("localhost", "DB_USERNAME", "DB_PASSWORD", "ddcar");
       mysqli_set_charset($conn, "utf8");
   ?>
   ```

### 7.3 การตั้งค่าฝั่ง Client (Client Configuration)
กำหนด URL เซิร์ฟเวอร์ Backend และข้อมูลหัวบิลของร้านที่ [www/js/config.js](file:///d:/MewP/ddcar/www/js/config.js):
```javascript
function getConfig(){
    // URL สำหรับเชื่อมต่อไปยัง Backend API
    localStorage.pathServer = "http://YOUR_SERVER_IP_OR_DOMAIN/dd-shop/";

    // ข้อมูลหัวบิลและที่อยู่ร้านค้า
    imgPath = "img/logo.jpg";
    shopDestination = "รถบริษัท DD MOTOR SPORT B-24, B-28 อาคารศรีวรจักร ถ.หลวงป้อมปราบศัตรูพ่าย";
    billshopName = "DD MOTOR SPORT";
    billshopDestination = "B-24, B-28 อาคารศรีวรจักร ถ.หลวงป้อมปราบศัตรูพ่าย";
    billshopTel = "โทร. 02-2265280 , 02-2222437";
}
```

### 7.4 การติดตั้ง Dependencies
```powershell
# ติดตั้ง Node Modules
npm install
```

### 7.5 การคอมไพล์และรันแอปพลิเคชัน

#### สำหรับ Desktop Web:
นำโฟลเดอร์ `www/` ไปวางบน Web Server (เช่น Apache, Nginx หรือ XAMPP) แล้วเปิดเข้าใช้งานผ่านเบราว์เซอร์:
```
http://localhost/ddcar/www/web_login.html
```

#### สำหรับ Mobile App (Android):
```powershell
# ซิงค์โค้ด Web Assets ไปยัง Android Project
npx cap sync android

# เปิดโปรเจกต์ใน Android Studio เพื่อ Run หรือ Build APK
npx cap open android
```
หรือคอมไพล์เป็นไฟล์ APK โดยตรงผ่าน Gradle Wrapper:
```powershell
cd android
./gradlew assembleDebug
```
ไฟล์ APK จะอยู่ที่ `android/app/build/outputs/apk/debug/app-debug.apk`

---

## 8. ข้อสังเกตและแนวทางพัฒนาต่อยอด (Security & Performance Guidelines)

> [!WARNING]
> **1. การป้องกัน SQL Injection**
> ไฟล์ PHP ส่วนใหญ่เดิมใช้การต่อสตริงตัวแปรเข้า SQL Command โดยตรง ควรทยอยปรับปรุงให้ใช้งาน **Prepared Statements (`$stmt = mysqli_prepare(...)`)** หรือกรองข้อมูลด้วย `mysqli_real_escape_string()` แบบที่ทำใน [add_prod.php](file:///d:/MewP/ddcar/www/php/add_prod.php) และ [search_prod_full.php](file:///d:/MewP/ddcar/www/php/search_prod_full.php) เพื่อป้องกันช่องโหว่ความปลอดภัย

> [!IMPORTANT]
> **2. การเก็บไฟล์รูปภาพสินค้า**
> ปัจจุบันบางหน้าบันทึกรูปภาพเป็น Base64 Data URL ลงในฟิลด์ `product.img` ในฐานข้อมูลโดยตรง ซึ่งทำให้ขนาดฐานข้อมูลขยายใหญ่และคิวรีช้าลงอย่างมาก แนะนำให้ปรับเป็นระบบอัปโหลดไฟล์รูปภาพลงโฟลเดอร์ `www/uploads/` หรือ Object Storage แล้วเก็บเฉพาะ URL/Path ลงในฐานข้อมูล

> [!TIP]
> **3. การลดปัญหา N+1 Query**
> สคริปต์คำนวณและรายงานบางตัว (เช่น [get_all_cost.php](file:///d:/MewP/ddcar/www/php/get_all_cost.php)) มีการวนลูป `while` แล้วส่งคำสั่ง `SELECT` ซ้ำตามจำนวนแถว สามารถปรับให้ใช้คำสั่ง `JOIN` ร่วมกับฟังก์ชันรวมกลุ่ม (`SUM()`, `GROUP BY`) ใน Query เดียว จะช่วยเพิ่มความเร็วในการโหลดข้อมูลขึ้นอย่างชัดเจน

> [!NOTE]
> **4. การบันทึกต้นทุนประวัติ (Historical Costing)**
> ในการคำนวณกำไรย้อนหลัง ควรบันทึกฟิลด์ `prod_cost` ณ เวลาที่เกิดการขายลงในตาราง `sale_order_item` เพิ่มเติม เพื่อให้การคำนวณกำไรสุทธิย้อนหลังไม่คลาดเคลื่อนหากสินค้ามีการปรับต้นทุนใหม่ในอนาคต

