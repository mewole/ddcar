const fs = require('fs');

function updateImportFile(filePath, label) {
    let content = fs.readFileSync(filePath, 'utf8');

    // Replace the list badge rendering
    const oldBadgePattern = /var sourceTxt = supName && supName\.trim\(\) !== ''\s*\?\s*'<span class="badge badge-primary[^>]*>[^<]*<\/span>'\s*:\s*'<span class="text-muted"[^>]*>-<\/span>';/g;
    const newBadgeStr = `var isValidSup = supName && supName.trim() !== '' && supName.trim() !== '-';
              var sourceTxt = isValidSup
                ? '<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.88rem;"><i class="fas fa-store mr-1"></i>' + supName + '</span>'
                : '<span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.82rem;">ไม่ระบุ</span>';`;

    if (oldBadgePattern.test(content)) {
        content = content.replace(oldBadgePattern, newBadgeStr);
        console.log(label + ': list badge updated');
    } else {
        console.log(label + ': list badge pattern not matched directly, checking lines');
        content = content.replace(
            `var sourceTxt = supName && supName.trim() !== ''\r\n                ? '<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.88rem;"><i class="fas fa-store mr-1"></i>' + supName + '</span>'\r\n                : '<span class="text-muted" style="font-size: 0.88rem;">-</span>';`,
            newBadgeStr
        );
        content = content.replace(
            `var sourceTxt = supName && supName.trim() !== ''\n                ? '<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.88rem;"><i class="fas fa-store mr-1"></i>' + supName + '</span>'\n                : '<span class="text-muted" style="font-size: 0.88rem;">-</span>';`,
            newBadgeStr
        );
    }

    // Replace modal header and print views
    content = content.replace(
        /var supHeader = \(firstRow\.supplier_name \|\| firstRow\.source_from \|\| '-'\);/g,
        `var supHeader = (firstRow.supplier_name && firstRow.supplier_name.trim() !== '' && firstRow.supplier_name !== '-') ? firstRow.supplier_name : (firstRow.source_from && firstRow.source_from.trim() !== '' && firstRow.source_from !== '-' ? firstRow.source_from : 'ไม่ระบุ');`
    );

    content = content.replace(
        /var sup = \(firstRow\.supplier_name \|\| firstRow\.source_from \|\| '-'\);/g,
        `var sup = (firstRow.supplier_name && firstRow.supplier_name.trim() !== '' && firstRow.supplier_name !== '-') ? firstRow.supplier_name : (firstRow.source_from && firstRow.source_from.trim() !== '' && firstRow.source_from !== '-' ? firstRow.source_from : 'ไม่ระบุ');`
    );

    content = content.replace(
        /var sup = obj\[0\]\.supplier_name \|\| obj\[0\]\.source_from \|\| '-';/g,
        `var sup = (obj[0].supplier_name && obj[0].supplier_name.trim() !== '' && obj[0].supplier_name !== '-') ? obj[0].supplier_name : (obj[0].source_from && obj[0].source_from.trim() !== '' && obj[0].source_from !== '-' ? obj[0].source_from : 'ไม่ระบุ');`
    );

    fs.writeFileSync(filePath, content, 'utf8');

    // Syntax check
    const scriptMatches = content.match(/<script[\s\S]*?<\/script>/gi) || [];
    let errs = 0;
    scriptMatches.forEach((m, idx) => {
        const tag = m.substring(0, m.indexOf('>') + 1);
        const code = m.replace(/<script[\s\S]*?>/i, '').replace(/<\/script>/i, '');
        if (code.trim() && !tag.includes('src=')) {
            try {
                new Function(code);
            } catch (e) {
                console.error(label + ' syntax error block ' + (idx + 1) + ':', e.message);
                errs++;
            }
        }
    });
    console.log(label + ' syntax: ' + (errs === 0 ? '100% VALID' : errs + ' errors'));
}

updateImportFile('c:/ddcar/www/web_mng_import_warehouse.html', 'web_mng_import_warehouse.html');
updateImportFile('c:/ddcar/www/web_mng_import.html', 'web_mng_import.html');
