/**
 * Minimal, zero-dependency ISO 18004 QR Code SVG generator (Byte mode, ECC Level M).
 * Supports payloads up to 213 UTF-8 bytes (Versions 1-10) for UPI deep links.
 */
(function (global) {
  'use strict';

  const EXP_TABLE = new Array(256);
  const LOG_TABLE = new Array(256);
  for (let i = 0; i < 8; i++) EXP_TABLE[i] = 1 << i;
  for (let i = 8; i < 256; i++) {
    EXP_TABLE[i] = EXP_TABLE[i - 4] ^ EXP_TABLE[i - 5] ^ EXP_TABLE[i - 6] ^ EXP_TABLE[i - 8];
  }
  for (let i = 0; i < 255; i++) LOG_TABLE[EXP_TABLE[i]] = i;

  function gexp(n) {
    while (n < 0) n += 255;
    while (n >= 256) n -= 255;
    return EXP_TABLE[n];
  }

  function glog(n) {
    if (n < 1) throw new Error('glog(' + n + ')');
    return LOG_TABLE[n];
  }

  function polyMul(a, b) {
    const out = new Array(a.length + b.length - 1).fill(0);
    for (let i = 0; i < a.length; i++) {
      for (let j = 0; j < b.length; j++) {
        if (a[i] !== 0 && b[j] !== 0) {
          out[i + j] ^= gexp(glog(a[i]) + glog(b[j]));
        }
      }
    }
    return out;
  }

  function polyMod(dividend, divisor) {
    const res = dividend.slice();
    while (res.length >= divisor.length) {
      if (res[0] !== 0) {
        const factor = glog(res[0]);
        for (let i = 0; i < divisor.length; i++) {
          res[i] ^= gexp(glog(divisor[i]) + factor);
        }
      }
      res.shift();
    }
    return res;
  }

  function rsGeneratorPoly(ecLen) {
    let g = [1];
    for (let i = 0; i < ecLen; i++) {
      g = polyMul(g, [1, gexp(i)]);
    }
    return g;
  }

  // ECC Level M RS block specs for Versions 1..10: [totalCodewords, ecPerBlock, [[count, dataPerBlock], ...]]
  const VERSION_SPECS_M = [
    null,
    [26, 10, [[1, 16]]],
    [44, 16, [[1, 28]]],
    [70, 26, [[1, 44]]],
    [100, 18, [[2, 32]]],
    [134, 24, [[2, 43]]],
    [172, 16, [[4, 27]]],
    [196, 18, [[4, 31]]],
    [242, 22, [[2, 38], [2, 39]]],
    [292, 22, [[3, 36], [2, 37]]],
    [346, 26, [[4, 43], [1, 44]]],
  ];

  const ALIGN_POS = [
    [],
    [],
    [6, 18],
    [6, 22],
    [6, 26],
    [6, 30],
    [6, 34],
    [6, 22, 38],
    [6, 24, 42],
    [6, 26, 46],
    [6, 28, 50],
  ];

  function bchTypeInfo(data) {
    let d = data << 10;
    const g = 0x537;
    while (bchDigit(d) - bchDigit(g) >= 0) {
      d ^= g << (bchDigit(d) - bchDigit(g));
    }
    return ((data << 10) | d) ^ 0x5412;
  }

  function bchTypeNumber(data) {
    let d = data << 12;
    const g = 0x1f25;
    while (bchDigit(d) - bchDigit(g) >= 0) {
      d ^= g << (bchDigit(d) - bchDigit(g));
    }
    return (data << 12) | d;
  }

  function bchDigit(data) {
    let digit = 0;
    while (data !== 0) {
      digit++;
      data >>>= 1;
    }
    return digit;
  }

  function utf8Bytes(str) {
    if (typeof TextEncoder !== 'undefined') {
      return Array.from(new TextEncoder().encode(str));
    }
    const out = [];
    for (let i = 0; i < str.length; i++) {
      let c = str.charCodeAt(i);
      if (c < 0x80) out.push(c);
      else if (c < 0x800) out.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f));
      else out.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f));
    }
    return out;
  }

  function encodeData(bytes) {
    let version = 0;
    for (let v = 1; v < VERSION_SPECS_M.length; v++) {
      const spec = VERSION_SPECS_M[v];
      let dataCap = 0;
      spec[2].forEach(([cnt, dLen]) => { dataCap += cnt * dLen; });
      const headerBits = v < 10 ? 12 : 20;
      if (bytes.length * 8 + headerBits <= dataCap * 8) {
        version = v;
        break;
      }
    }
    if (!version) return null;

    const spec = VERSION_SPECS_M[version];
    let totalDataCodewords = 0;
    spec[2].forEach(([cnt, dLen]) => { totalDataCodewords += cnt * dLen; });

    const bits = [];
    const pushBits = (val, len) => {
      for (let i = len - 1; i >= 0; i--) bits.push((val >>> i) & 1);
    };

    pushBits(4, 4); // Byte mode
    pushBits(bytes.length, version < 10 ? 8 : 16);
    for (let i = 0; i < bytes.length; i++) pushBits(bytes[i], 8);

    const maxBits = totalDataCodewords * 8;
    const termLen = Math.min(4, maxBits - bits.length);
    pushBits(0, termLen);
    while (bits.length % 8 !== 0) bits.push(0);

    const padBytes = [0xec, 0x11];
    let p = 0;
    while (bits.length < maxBits) {
      pushBits(padBytes[p % 2], 8);
      p++;
    }

    const dataBytes = [];
    for (let i = 0; i < bits.length; i += 8) {
      let b = 0;
      for (let j = 0; j < 8; j++) b = (b << 1) | bits[i + j];
      dataBytes.push(b);
    }

    const ecPerBlock = spec[1];
    const rsPoly = rsGeneratorPoly(ecPerBlock);
    const dataBlocks = [];
    const ecBlocks = [];
    let offset = 0;
    let maxDataLen = 0;

    spec[2].forEach(([cnt, dLen]) => {
      if (dLen > maxDataLen) maxDataLen = dLen;
      for (let i = 0; i < cnt; i++) {
        const block = dataBytes.slice(offset, offset + dLen);
        offset += dLen;
        dataBlocks.push(block);
        const padded = block.concat(new Array(ecPerBlock).fill(0));
        ecBlocks.push(polyMod(padded, rsPoly));
      }
    });

    const codewords = [];
    for (let i = 0; i < maxDataLen; i++) {
      for (let b = 0; b < dataBlocks.length; b++) {
        if (i < dataBlocks[b].length) codewords.push(dataBlocks[b][i]);
      }
    }
    for (let i = 0; i < ecPerBlock; i++) {
      for (let b = 0; b < ecBlocks.length; b++) {
        codewords.push(ecBlocks[b][i]);
      }
    }

    return { version, codewords };
  }

  function buildMatrix(version, codewords, maskPattern) {
    const n = version * 4 + 17;
    const modules = Array.from({ length: n }, () => new Array(n).fill(null));

    const placeFinder = (r0, c0) => {
      for (let r = -1; r <= 7; r++) {
        if (r0 + r < 0 || r0 + r >= n) continue;
        for (let c = -1; c <= 7; c++) {
          if (c0 + c < 0 || c0 + c >= n) continue;
          const dark =
            (r >= 0 && r <= 6 && (c === 0 || c === 6)) ||
            (c >= 0 && c <= 6 && (r === 0 || r === 6)) ||
            (r >= 2 && r <= 4 && c >= 2 && c <= 4);
          modules[r0 + r][c0 + c] = dark;
        }
      }
    };

    placeFinder(0, 0);
    placeFinder(0, n - 7);
    placeFinder(n - 7, 0);

    // Alignment patterns
    const align = ALIGN_POS[version] || [];
    for (let i = 0; i < align.length; i++) {
      for (let j = 0; j < align.length; j++) {
        const row = align[i];
        const col = align[j];
        if (modules[row][col] !== null) continue;
        for (let r = -2; r <= 2; r++) {
          for (let c = -2; c <= 2; c++) {
            modules[row + r][col + c] =
              r === -2 || r === 2 || c === -2 || c === 2 || (r === 0 && c === 0);
          }
        }
      }
    }

    // Timing patterns
    for (let i = 8; i < n - 8; i++) {
      if (modules[i][6] === null) modules[i][6] = i % 2 === 0;
      if (modules[6][i] === null) modules[6][i] = i % 2 === 0;
    }

    // Format info placeholders
    const typeBits = bchTypeInfo((0 << 3) | maskPattern); // ECC M = 00
    for (let i = 0; i < 15; i++) {
      const mod = ((typeBits >> i) & 1) === 1;
      if (i < 6) modules[i][8] = mod;
      else if (i < 8) modules[i + 1][8] = mod;
      else modules[n - 15 + i][8] = mod;

      if (i < 8) modules[8][n - i - 1] = mod;
      else if (i < 9) modules[8][15 - i] = mod;
      else modules[8][15 - i - 1] = mod;
    }
    modules[n - 8][8] = true;

    if (version >= 7) {
      const vBits = bchTypeNumber(version);
      for (let i = 0; i < 18; i++) {
        const mod = ((vBits >> i) & 1) === 1;
        modules[Math.floor(i / 3)][(i % 3) + n - 11] = mod;
        modules[(i % 3) + n - 11][Math.floor(i / 3)] = mod;
      }
    }

    const maskFn = (r, c) => {
      switch (maskPattern) {
        case 0: return (r + c) % 2 === 0;
        case 1: return r % 2 === 0;
        case 2: return c % 3 === 0;
        case 3: return (r + c) % 3 === 0;
        case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
        case 5: return ((r * c) % 2) + ((r * c) % 3) === 0;
        case 6: return (((r * c) % 2) + ((r * c) % 3)) % 2 === 0;
        case 7: return (((r * c) % 3) + ((r + c) % 2)) % 2 === 0;
        default: return false;
      }
    };

    let inc = -1;
    let row = n - 1;
    let bitIndex = 7;
    let byteIndex = 0;

    for (let col = n - 1; col > 0; col -= 2) {
      if (col === 6) col--;
      while (true) {
        for (let c = 0; c < 2; c++) {
          if (modules[row][col - c] === null) {
            let dark = false;
            if (byteIndex < codewords.length) {
              dark = ((codewords[byteIndex] >>> bitIndex) & 1) === 1;
            }
            if (maskFn(row, col - c)) dark = !dark;
            modules[row][col - c] = dark;
            bitIndex--;
            if (bitIndex === -1) {
              byteIndex++;
              bitIndex = 7;
            }
          }
        }
        row += inc;
        if (row < 0 || n <= row) {
          row -= inc;
          inc = -inc;
          break;
        }
      }
    }

    return modules;
  }

  function penaltyScore(modules) {
    const n = modules.length;
    let lost = 0;
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        let same = 0;
        const dark = modules[r][c];
        for (let dr = -1; dr <= 1; dr++) {
          if (r + dr < 0 || r + dr >= n) continue;
          for (let dc = -1; dc <= 1; dc++) {
            if (c + dc < 0 || c + dc >= n || (dr === 0 && dc === 0)) continue;
            if (dark === modules[r + dr][c + dc]) same++;
          }
        }
        if (same > 5) lost += 3 + same - 5;
      }
    }
    for (let r = 0; r < n - 1; r++) {
      for (let c = 0; c < n - 1; c++) {
        let count = 0;
        if (modules[r][c]) count++;
        if (modules[r + 1][c]) count++;
        if (modules[r][c + 1]) count++;
        if (modules[r + 1][c + 1]) count++;
        if (count === 0 || count === 4) lost += 3;
      }
    }
    let darkCount = 0;
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (modules[r][c]) darkCount++;
      }
    }
    lost += (Math.abs((100 * darkCount) / (n * n) - 50) / 5) * 10;
    return lost;
  }

  function generateQrMatrix(text) {
    const encoded = encodeData(utf8Bytes(String(text || '')));
    if (!encoded) return null;
    let bestMatrix = null;
    let bestScore = Infinity;
    for (let m = 0; m < 8; m++) {
      const mat = buildMatrix(encoded.version, encoded.codewords, m);
      const score = penaltyScore(mat);
      if (score < bestScore) {
        bestScore = score;
        bestMatrix = mat;
      }
    }
    return bestMatrix;
  }

  global.generateRealUpiQrSvg = function (text, size = 180) {
    const matrix = generateQrMatrix(text);
    if (!matrix) {
      return '<div style="font-size:11px; color:#64748b; padding:12px;">UPI QR unavailable</div>';
    }
    const n = matrix.length;
    const pad = 2;
    const vb = n + pad * 2;
    let paths = '';
    for (let r = 0; r < n; r++) {
      for (let c = 0; c < n; c++) {
        if (matrix[r][c]) {
          paths += `M${c + pad},${r + pad}h1v1h-1z`;
        }
      }
    }
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${vb} ${vb}" shape-rendering="crispEdges" role="img" aria-label="UPI Payment QR Code" style="display:block; margin:0 auto; background:#ffffff; border-radius:10px; border:1px solid #e2e8f0;"><rect width="${vb}" height="${vb}" fill="#ffffff"/><path d="${paths}" fill="#0f172a"/></svg>`;
  };
})(typeof window !== 'undefined' ? window : globalThis);
