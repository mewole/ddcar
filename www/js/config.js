function getConfig(){
    // PRD & Path Config
    if (window.location.protocol === 'http:' || window.location.protocol === 'https:') {
        var path = window.location.pathname;
        var subDir = path.substring(0, path.lastIndexOf('/') + 1);
        localStorage.pathServer = window.location.origin + subDir;
    } else if (!localStorage.pathServer || localStorage.pathServer === "undefined" || localStorage.pathServer === "null") {
        localStorage.pathServer = "http://ddaccessory.trueddns.com:24330/dd-shop/";
    }

    //User config
    //logo
    imgPath = "img/logo.jpg";
    //for a4
    shopDestination = "รถบริษัท DD MOTOR SPORT B-24, B-28 อาคารศรีวรจักร ถ.หลวงป้อมปราบศัตรูพ่าย";
    //for bill
    billshopName = "DD MOTOR SPORT";
    billshopDestination = "B-24, B-28 อาคารศรีวรจักร ถ.หลวงป้อมปราบศัตรูพ่าย";
    billshopTel = "โทร. 02-2265280 , 02-2222437";    
}
getConfig();