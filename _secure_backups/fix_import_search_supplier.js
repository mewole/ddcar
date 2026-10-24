const fs = require('fs');

const files = ['www/web_mng_import_warehouse.html', 'www/web_mng_import.html'];

files.forEach(f => {
  let content = fs.readFileSync(f, 'utf8');

  // Regex to match the search function's supplier block
  const pattern = /var supName = field\.supplier_name \|\| field\.source_from;\s*var sourceTxt = supName && supName\.trim\(\) !== ''\s*\?\s*'<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0\.88rem;"><i class="fas fa-store mr-1"><\/i>'\s*\+\s*supName\s*\+\s*'<\/span>'\s*:\s*'<span class="text-muted" style="font-size: 0\.88rem;">-<\/span>';/g;

  const replacement = `var supName = field.supplier_name || field.source_from;
              var isValidSup = supName && supName.trim() !== '' && supName.trim() !== '-';
              var sourceTxt = isValidSup
                ? '<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.88rem;"><i class="fas fa-store mr-1"></i>' + supName + '</span>'
                : '<span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.82rem;">ไม่ระบุ</span>';`;

  if (pattern.test(content)) {
    content = content.replace(pattern, replacement);
    fs.writeFileSync(f, content, 'utf8');
    console.log(f + ': successfully updated search supplier badge');
  } else {
    console.log(f + ': pattern not found, trying manual line replacement');
    // Manual search for the block
    const oldLines = `var sourceTxt = supName && supName.trim() !== ''\n                ? '<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.88rem;"><i class="fas fa-store mr-1"></i>' + supName + '</span>'\n                : '<span class="text-muted" style="font-size: 0.88rem;">-</span>';`;
    const oldLinesCRLF = oldLines.replace(/\n/g, '\r\n');
    const newLines = `var isValidSup = supName && supName.trim() !== '' && supName.trim() !== '-';\n              var sourceTxt = isValidSup\n                ? '<span class="badge badge-primary font-weight-bold px-2 py-1" style="font-size: 0.88rem;"><i class="fas fa-store mr-1"></i>' + supName + '</span>'\n                : '<span class="badge badge-light border text-muted px-2 py-1" style="font-size: 0.82rem;">ไม่ระบุ</span>';`;
    const newLinesCRLF = newLines.replace(/\n/g, '\r\n');

    if (content.includes(oldLines)) {
      content = content.replace(oldLines, newLines);
      fs.writeFileSync(f, content, 'utf8');
      console.log(f + ': manual LF replacement successful');
    } else if (content.includes(oldLinesCRLF)) {
      content = content.replace(oldLinesCRLF, newLinesCRLF);
      fs.writeFileSync(f, content, 'utf8');
      console.log(f + ': manual CRLF replacement successful');
    } else {
      console.error(f + ': could not replace!');
    }
  }

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
        console.error(f + ' syntax error block ' + (idx + 1) + ':', e.message);
        errs++;
      }
    }
  });
  if (errs === 0) {
    console.log(f + ' syntax: 100% VALID');
  }
});
