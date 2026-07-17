// Gera ícones PNG para o PWA usando apenas módulos nativos do Node.js
// Cria um PNG mínimo válido com cor sólida #1f5e7a

const fs = require('fs');
const path = require('path');

function createPNG(size, outputPath) {
    // PNG header + IHDR + IDAT + IEND
    function crc32(buf) {
        let c = 0xFFFFFFFF;
        const table = [];
        for (let i = 0; i < 256; i++) {
            let v = i;
            for (let j = 0; j < 8; j++) v = (v & 1) ? 0xEDB88320 ^ (v >>> 1) : v >>> 1;
            table[i] = v;
        }
        for (let i = 0; i < buf.length; i++) c = table[(c ^ buf[i]) & 0xFF] ^ (c >>> 8);
        return (c ^ 0xFFFFFFFF) >>> 0;
    }

    function chunk(type, data) {
        const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
        const t = Buffer.from(type);
        const crcBuf = Buffer.concat([t, data]);
        const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(crcBuf));
        return Buffer.concat([len, t, data, crc]);
    }

    function adler32(buf) {
        let s1 = 1, s2 = 0;
        for (let i = 0; i < buf.length; i++) { s1 = (s1 + buf[i]) % 65521; s2 = (s2 + s1) % 65521; }
        return ((s2 * 65536) + s1) >>> 0;
    }

    function deflateStore(data) {
        // zlib wrapper with DEFLATE store (no compression)
        const cmf = 0x78, flg = 0x01;
        const blocks = [];
        const BSIZE = 65535;
        for (let i = 0; i < data.length; i += BSIZE) {
            const slice = data.slice(i, i + BSIZE);
            const last = (i + BSIZE >= data.length) ? 1 : 0;
            const hdr = Buffer.alloc(5);
            hdr[0] = last; hdr.writeUInt16LE(slice.length, 1); hdr.writeUInt16LE(~slice.length & 0xFFFF, 3);
            blocks.push(hdr, slice);
        }
        const adl = Buffer.alloc(4); adl.writeUInt32BE(adler32(data));
        return Buffer.concat([Buffer.from([cmf, flg]), ...blocks, adl]);
    }

    // Build raw image data (RGBA)
    const r = 0x1f, g = 0x5e, b = 0x7a, a = 0xff;
    const rows = [];
    for (let y = 0; y < size; y++) {
        const row = Buffer.alloc(1 + size * 4);
        row[0] = 0; // filter type None
        for (let x = 0; x < size; x++) {
            row[1 + x * 4] = r; row[2 + x * 4] = g; row[3 + x * 4] = b; row[4 + x * 4] = a;
        }
        rows.push(row);
    }
    const raw = Buffer.concat(rows);
    const idat = deflateStore(raw);

    const sig = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(size, 0); ihdr.writeUInt32BE(size, 4);
    ihdr[8] = 8; ihdr[9] = 6; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;

    const png = Buffer.concat([sig, chunk('IHDR', ihdr), chunk('IDAT', idat), chunk('IEND', Buffer.alloc(0))]);
    fs.writeFileSync(outputPath, png);
    console.log('Criado: ' + path.basename(outputPath));
}

const dir = path.join(__dirname, 'icons');
[96, 144, 192, 512].forEach(function(s) {
    createPNG(s, path.join(dir, 'icon-' + s + '.png'));
});
console.log('Icones gerados!');
