async function scan(arg, type, ware_id) {
    const CapacitorBarcodeScanner = window.Capacitor?.Plugins?.CapacitorBarcodeScanner || window.CapacitorBarcodeScanner;

    if (!CapacitorBarcodeScanner) {
        console.error("CapacitorBarcodeScanner plugin is not available.");
        return;
    }
    try {
        if (window.Capacitor && Capacitor.getPlatform() === 'web') {
            console.log("Running on web / unsupported environment");
        }
        // hint: 17 = รับบาร์โค้ดทุกรูปแบบทั้ง 1D และ 2D (QR, Barcode)
        const result = await CapacitorBarcodeScanner.scanBarcode({
            hint: 17,
            scanInstructions: "วางบาร์โค้ดหรือคิวอาร์โค้ดให้อยู่ในกรอบเพื่อสแกน"
        });

        if (result && result.ScanResult) {
            var value = result.ScanResult;
            
            if (value.includes("[transfer]")) {
                var indexT = value.indexOf("]");
                indexT += 1;
                var valueSS = value.substring(indexT);
                var groupStr = valueSS.split("|");
                var prod_id = groupStr[0].split(",");
                var cost = groupStr[1].split(",");
                var amt = groupStr[2].split(",");
                var arr_data = [];
                var sum = 0;

                for (var i = 0; i < prod_id.length; i++) {
                    var item = {};
                    item["prod_id"] = prod_id[i];
                    item["cost"] = cost[i];
                    item["amt"] = amt[i];
                    item["addr"] = "";
                    arr_data.push(item);
                    var sum_prod = cost[i] * amt[i];
                    sum += parseInt(sum_prod);
                }

                var d = new Date($.now());         
                var datetime = d.getFullYear() + "-" + (d.getMonth() + 1) + "-" + d.getDate() + " " + d.getHours() + ":" + d.getMinutes() + ":" + d.getSeconds();
                var json_string = JSON.stringify(arr_data);
                var dataString = "JSON=" + json_string + "&ware_id=" + (ware_id || '') + "&date=" + datetime
                + "&sum=" + sum + "&user_id=" + (localStorage.user_id || '') + "&type=" + (type || '');

                $.ajax({
                    type: "POST",
                    url: localStorage.pathServer + "php/stock_in.php",           
                    data: dataString,               
                    beforeSend: function(){
                        $(".overlay").prop('hidden', false);
                    },             
                    success: function(data){  
                        $(".overlay").prop('hidden', true);              
                        if (data == "success") {                       
                            $.confirm({
                                title: 'นำสินค้าเข้า ' + (type || ''),
                                content: 'สำเร็จ',
                                backgroundDismiss: true,
                                buttons: {
                                    formSubmit: {
                                        text: 'ตกลง',
                                        btnClass: 'btn-danger',
                                        action: function () {
                                            document.elementFromPoint(0, 0)?.click();
                                        }
                                    }
                                }
                            });
                        } else {
                            $.confirm({
                                title: 'พบข้อผิดพลาด',
                                content: 'ลองอีกครั้ง',
                                backgroundDismiss: true,
                                buttons: {
                                    formSubmit: {
                                        text: 'ตกลง',
                                        btnClass: 'btn-danger',
                                        action: function () {
                                            document.elementFromPoint(0, 0)?.click();
                                        }
                                    }
                                }
                            });
                        }
                    },
                    error: function() {
                        $(".overlay").prop('hidden', true);
                    }               
                });
            } else {
                $(".overlay").prop('hidden', false);
                $.ajax({
                    type: "POST",
                    url: localStorage.pathServer + "php/check_amt_prod.php",
                    data: {'qr': value},
                    success: function(data){  
                        var obj;
                        try {
                            obj = (typeof data === 'object') ? data : jQuery.parseJSON(data);
                        } catch (e) {
                            obj = [];
                        }

                        if (obj && obj.length > 0) {
                            // Asynchronous fetch of place details to prevent UI thread lock
                            var placeRequests = obj.map(function(item) {
                                var pId = item.prod_id;
                                var id = item.id;
                                var placeType = item.type || item.place_type || item[9] || item[8] || item[0];
                                var datastr = (placeType == "ware") ? {'prod_id': pId, 'ware_id': id} : {'prod_id': pId, 'shop_id': id};
                                
                                return $.ajax({
                                    type: "POST",
                                    url: localStorage.pathServer + "php/get_place.php",
                                    data: datastr
                                }).then(function(resData) {
                                    var addrArr = [];
                                    try {
                                        var pObj = (typeof resData === 'object') ? resData : jQuery.parseJSON(resData);
                                        $.each(pObj, function(idx, field) {
                                            if (field.place) addrArr.push(field.place);
                                        });
                                    } catch(err) {}
                                    item._addr_clean = addrArr.length ? addrArr.join(', ') : '-';
                                    return item;
                                }).catch(function() {
                                    item._addr_clean = '-';
                                    return item;
                                });
                            });

                            Promise.all(placeRequests).then(function(items) {
                                $(".overlay").prop('hidden', true);

                                var msg = "";
                                var price = "", pricesend = "", cost = "", img = "", name = "";
                                $.each(items, function(i, item) {
                                    price = item.prod_price;
                                    pricesend = item.prod_pricesend;
                                    cost = item.prod_cost;
                                    img = item.img; 
                                    name = item.prod_name;
                                    var placeType = item.type || item.place_type || item[9] || item[8] || item[0];
                                    var type_label = (placeType == "ware") ? "โกดัง" : "หน้าร้าน";
                                    if (item.ware_name) { type_label = item.ware_name; }
                                    else if (item.shop_name) { type_label = item.shop_name; }
                                    else if (item[0] && item[0] !== "ware" && item[0] !== "shop") { type_label = item[0]; }

                                    msg += '<div class="row align-items-center" style="margin-bottom: 8px; padding: 6px 12px; background: #f8fafc; border-radius: 8px; font-size: 14px;">' +
                                           '<div class="col-6 text-left" style="font-weight: 600; color: #1e293b;">' + type_label + '</div>' +
                                           '<div class="col-6 text-right"><span style="color: #da251c; font-weight: 700;">' + item.amount + '</span> <small style="color: #64748b;">(' + item._addr_clean + ')</small></div>' +
                                           '</div>';
                                });

                                var msg_price;					
                                if (localStorage.user_role == "พนักงาน") {
                                    msg_price = '<div style="font-size: 14px; margin-bottom: 6px;"><span class="badge badge-light border">ส่ง: ' + pricesend + '</span> <span class="badge badge-light border">ปลีก: ' + price + '</span></div>';
                                } else {
                                    msg_price = '<div style="font-size: 14px; margin-bottom: 6px;"><span class="badge badge-light border">ทุน: ' + cost + '</span> <span class="badge badge-light border">ส่ง: ' + pricesend + '</span> <span class="badge badge-light border">ปลีก: ' + price + '</span></div>';
                                }	
                                var top_msg = '<div class="text-center" style="margin-bottom: 12px;">' +
                                              msg_price +
                                              '<img src="' + img + '" id="img" alt="" onclick="show_pic()" style="width: 220px; height: 220px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">' +
                                              '</div>'; 
                                
                                $.confirm({
                                    title: name || 'ข้อมูลสินค้า',
                                    content: top_msg + msg,
                                    backgroundDismiss: true,
                                    buttons: {
                                        formSubmit: {
                                            text: 'ปิด',
                                            btnClass: 'btn-danger',
                                            action: function () {
                                                document.elementFromPoint(0, 0)?.click();
                                            }
                                        }
                                    }
                                });
                            });
                        } else {
                            $(".overlay").prop('hidden', true);
                            $.confirm({
                                title: "ไม่มีสินค้าในโกดัง/หน้าร้าน",
                                content: "ไม่พบข้อมูลสต็อกของสินค้ารายการนี้",
                                backgroundDismiss: true,
                                buttons: {
                                    formSubmit: {
                                        text: 'ปิด',
                                        btnClass: 'btn-danger',
                                        action: function () {
                                            document.elementFromPoint(0, 0)?.click();
                                        }
                                    }
                                }
                            });  
                        }                                                                                                                  
                    },
                    error: function() {
                        $(".overlay").prop('hidden', true);
                        $.confirm({
                            title: 'พบข้อผิดพลาด',
                            content: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้',
                            backgroundDismiss: true,
                            buttons: {
                                formSubmit: {
                                    text: 'ตกลง',
                                    btnClass: 'btn-danger'
                                }
                            }
                        });
                    }               
                });
            }       
        } else {
            if (arg == "index") {
                window.location = "index.html";
            }
        }
    } catch (error) {
        console.error("Barcode Scan Error:", error);
        $.confirm({
            title: 'พบข้อผิดพลาด',
            content: 'ไม่สามารถเปิดกล้องหรือสแกนได้: ' + (error.message || 'กรุณาลองใหม่อีกครั้ง'),
            backgroundDismiss: true,
            buttons: {
                formSubmit: {
                    text: 'ตกลง',
                    btnClass: 'btn-danger',
                    action: function () {}
                }
            }
        });
    }
}

function show_pic(){
    localStorage.img = $('#img').attr('src');           
    window.location = "show_pic.html";   
}