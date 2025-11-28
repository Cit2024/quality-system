/* PDFReportGenerator.js - FIXED VERSION
   Browser-only. Almarai embedded via jsDelivr fallback.
   Addresses are fixed constants embedded in this file (mandatory in every report).
*/

(function (root) {
  'use strict';

  // --- CONFIG: fixed addresses (mandatory, always shown in header) ---
  const ADDRESSES = [
    "وزارة الصناعة و المعادن",
    "كلية التقنية الصناعية"
  ];

  // --- CDN paths ---
  const DEFAULT_PDFMAKE = 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js';
  const DEFAULT_VFS = 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js';
  const JSDELIVR_ALMARAI_REGULAR = 'https://cdn.jsdelivr.net/gh/google/fonts@main/ofl/almarai/Almarai-Regular.ttf';
  const JSDELIVR_ALMARAI_BOLD    = 'https://cdn.jsdelivr.net/gh/google/fonts@main/ofl/almarai/Almarai-Bold.ttf';

  // Track if Almarai is loaded
  let almaraiLoaded = false;

  // --- utilities ---
  function loadScript(url) {
    return new Promise((resolve, reject) => {
      if (document.querySelector('script[data-pdfgen="'+url+'"]')) return resolve();
      const s = document.createElement('script');
      s.src = url;
      s.async = true;
      s.setAttribute('data-pdfgen', url);
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('Failed loading ' + url));
      document.head.appendChild(s);
    });
  }

  function arrayBufferToBase64(buffer) {
    let binary = '';
    const bytes = new Uint8Array(buffer);
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
      binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
  }

  async function fetchBinaryAsBase64(url) {
    const res = await fetch(url, { mode: 'cors' });
    if (!res.ok) throw new Error('Failed to fetch font: ' + url + ' (status ' + res.status + ')');
    const buf = await res.arrayBuffer();
    return arrayBufferToBase64(buf);
  }

  function safeNum(v) {
    if (v === null || v === undefined) return 0;
    if (typeof v === 'number') return v;
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  function roundDecimal(num, places = 2) {
    const val = safeNum(num);
    return Math.round(val * Math.pow(10, places)) / Math.pow(10, places);
  }

  function reverseArabicWords(text) {
    if (!text) return text;
    const str = String(text);
    if (!hasArabicText(str)) return str;
    // Reverse word order for Arabic text
    const words = str.split(' ');
    return words.reverse().join(' ');
  }

  function hasArabicText(s) {
    if (!s) return false;
    return /[\u0600-\u06FF\u0750-\u077F]/.test(String(s));
  }

  // create a text node that applies RTL/LTR and alignment automatically for Arabic
  function makeTextNode(value, extra = {}) {
    const v = (value === null || value === undefined) ? '' : String(value);
    // Reverse Arabic word order for proper display
    const displayText = hasArabicText(v) ? reverseArabicWords(v) : v;
    const node = { text: displayText };
    if (hasArabicText(v)) {
      node.direction = 'rtl';
      node.alignment = 'right';
      node.font = getFont();
    }
    return Object.assign(node, extra);
  }

  function percent(value, total) {
    if (!total) return 0;
    return roundDecimal((safeNum(value) / total) * 100, 2);
  }

  function progressBarCanvas(width, height, fraction, bgColor = '#e9f2fb', fgColor = '#d97757') {
    const fullW = Math.max(1, Math.floor(width));
    const fillW = Math.max(0, Math.floor(fullW * Math.min(1, Math.max(0, fraction))));
    return {
      canvas: [
        { type: 'rect', x: 0, y: 0, w: fullW, h: height, r: 2, color: bgColor },
        { type: 'rect', x: 0, y: 0, w: fillW, h: height, r: 2, color: fgColor }
      ],
      margin: [0, 2, 0, 2]
    };
  }

  // --- fonts embedding for Almarai ---
  async function embedAlmaraiFontsIfRequested(pdfMakeObj, almaraiOpts) {
    if (!almaraiOpts) return false;
    let regularUrl = null, boldUrl = null;
    if (almaraiOpts === true) {
      regularUrl = JSDELIVR_ALMARAI_REGULAR;
      boldUrl    = JSDELIVR_ALMARAI_BOLD;
    } else if (typeof almaraiOpts === 'object') {
      regularUrl = almaraiOpts.regularUrl || JSDELIVR_ALMARAI_REGULAR;
      boldUrl    = almaraiOpts.boldUrl    || JSDELIVR_ALMARAI_BOLD;
    } else {
      return false;
    }

    pdfMakeObj.vfs = pdfMakeObj.vfs || {};
    pdfMakeObj.fonts = pdfMakeObj.fonts || {};

    try {
      const regB64 = await fetchBinaryAsBase64(regularUrl);
      const regKey = 'Almarai-Regular.ttf';
      pdfMakeObj.vfs[regKey] = regB64;

      let boldB64;
      if (boldUrl && boldUrl !== regularUrl) {
        try { boldB64 = await fetchBinaryAsBase64(boldUrl); } catch (e) { boldB64 = regB64; }
      } else boldB64 = regB64;

      const boldKey = 'Almarai-Bold.ttf';
      pdfMakeObj.vfs[boldKey] = boldB64;

      pdfMakeObj.fonts.Almarai = {
        normal: regKey,
        bold: boldKey,
        italics: regKey,
        bolditalics: boldKey
      };

      almaraiLoaded = true;
      console.log('Almarai fonts loaded successfully');
      return true;
    } catch (err) {
      console.warn('Failed to embed Almarai fonts:', err);
      almaraiLoaded = false;
      return false;
    }
  }

  // Ensure pdfMake is loaded
  async function ensurePdfMake(opts = {}) {
    if (window.pdfMake && window.pdfMake.createPdf) {
      if (opts.customVfsObj && typeof opts.customVfsObj === 'object') {
        window.pdfMake.vfs = opts.customVfsObj;
      }
      if (opts.almarai) {
        await embedAlmaraiFontsIfRequested(window.pdfMake, opts.almarai);
      }
      return;
    }

    await loadScript(DEFAULT_PDFMAKE);
    try { await loadScript(DEFAULT_VFS); } catch (e) { console.warn('Could not load default vfs_fonts:', e); }

    if (opts.customVfsObj && typeof opts.customVfsObj === 'object') {
      window.pdfMake.vfs = opts.customVfsObj;
    }
    if (opts.vfsUrl) {
      try { await loadScript(opts.vfsUrl); } catch (e) { console.warn('Failed to load custom vfsUrl:', e); }
    }
    if (!window.pdfMake || !window.pdfMake.createPdf) {
      throw new Error('pdfMake not available after loading scripts.');
    }

    if (opts.almarai) {
      await embedAlmaraiFontsIfRequested(window.pdfMake, opts.almarai);
    }
  }

  // Helper to get font name
  function getFont() {
    return almaraiLoaded ? 'Almarai' : undefined;
  }

  // --- header builder uses fixed ADDRESSES constant ---
  function buildHeader(info) {
    const addrStack = ADDRESSES.map(a => ({
      text: String(a),
      style: 'address',
      alignment: 'center',
      direction: hasArabicText(a) ? 'rtl' : 'ltr',
      font: getFont()
    }));

    return {
      stack: [
        { stack: addrStack, margin: [0, 0, 0, 8] },
        {
          columns: [
            { 
              width: '*', 
              stack: [
                { text: info.evaluationTitle || 'Evaluation Report', style: 'title', font: getFont() },
                { text: info.reportName || '', style: 'subtitle', font: getFont() }
              ]
            },
            { 
              width: 'auto', 
              stack: [
                { text: `Participants: ${safeNum(info.numParticipants)}`, style: 'meta' },
                { text: `Date: ${new Date().toLocaleDateString()}`, style: 'meta' }
              ], 
              alignment: 'right' 
            }
          ],
          columnGap: 10,
          margin: [0, 0, 0, 6]
        }
      ]
    };
  }

  // --- question rendering (improved card layout) ---
  function renderQuestionCard(q, index, totalParticipants, showAverage) {
    const qNumber = { text: `Q${index}`, style: 'qBadge', margin: [0, 0, 6, 0] };
    const qTitleParts = [];
    
    if (hasArabicText(q.arabic)) {
      qTitleParts.push({ 
        columns: [
          qNumber, 
          { text: q.arabic || '', style: 'qTitle', direction: 'rtl', font: getFont() }
        ] 
      });
      if (q.english) {
        qTitleParts.push({ text: q.english, style: 'qSubtitle', margin: [24, 4, 0, 0] });
      }
    } else {
      qTitleParts.push({ 
        columns: [
          qNumber, 
          { text: q.english || q.arabic || '', style: 'qTitle' }
        ] 
      });
      if (q.arabic) {
        qTitleParts.push({ 
          text: q.arabic, 
          style: 'qSubtitle', 
          direction: 'rtl', 
          margin: [24, 4, 0, 0], 
          font: getFont() 
        });
      }
    }

    const summary = [];
    if (showAverage) {
      const avg = roundDecimal(q.average, 2);
      const max = safeNum(q.max_score) || '';
      const frac = (max && max > 0) ? Math.min(1, avg / max) : Math.min(1, avg / (totalParticipants || 1));
      summary.push({
        columns: [
          { width: '*', text: `Average: ${avg}${max ? ` / ${max}` : ''}`, style: 'smallMeta' },
          { width: 120, stack: [ progressBarCanvas(120, 8, frac) ] }
        ],
        margin: [0, 6, 0, 0]
      });
    }

    let distTable = null;
    if (Array.isArray(q.distribution) && q.distribution.length) {
      const body = [[
        makeTextNode('Option', { style: 'tableHeader' }), 
        makeTextNode('Count', { style: 'tableHeader' }), 
        makeTextNode('Percent', { style: 'tableHeader' })
      ]];
      const total = q.distribution.reduce((s, r) => s + safeNum(r.count), 0) || totalParticipants || 0;
      q.distribution.forEach(row => body.push([ 
        makeTextNode(row.option), 
        makeTextNode(safeNum(row.count)), 
        makeTextNode(`${percent(row.count, total)} %`)
      ]));
      distTable = { 
        style: 'cardTable', 
        table: { widths: ['*', 60, 70], body }, 
        layout: { 
          fillColor: (rowIndex)=> rowIndex===0? '#f5f9ff':null, 
          hLineWidth:()=>0.5, 
          vLineWidth:()=>0, 
          hLineColor:()=> '#e6eef7' 
        }, 
        margin: [0,6,0,0] 
      };
    } else if (q.counts && typeof q.counts === 'object') {
      const keys = Object.keys(q.counts);
      if (keys.length) {
        const body = [[
          makeTextNode('Option', { style: 'tableHeader' }), 
          makeTextNode('Count', { style: 'tableHeader' }), 
          makeTextNode('Percent', { style: 'tableHeader' })
        ]];
        const total = keys.reduce((s,k)=> s+safeNum(q.counts[k]), 0) || totalParticipants || 0;
        keys.forEach(k => body.push([ 
          makeTextNode(k), 
          makeTextNode(safeNum(q.counts[k])), 
          makeTextNode(`${percent(q.counts[k], total)} %`)
        ]));
        distTable = { 
          style: 'cardTable', 
          table: { widths: ['*',60,70], body }, 
          layout: { 
            fillColor: (rowIndex)=> rowIndex===0? '#f5f9ff':null, 
            hLineWidth:()=>0.5, 
            vLineWidth:()=>0, 
            hLineColor:()=> '#e6eef7' 
          }, 
          margin: [0,6,0,0] 
        };
      }
    }

    let responsesBlock = null;
    if (Array.isArray(q.responses) && q.responses.length) {
      const sample = q.responses.slice(0,3).map((r,i)=> ({ 
        // keep numbering but ensure direction is set for Arabic responses
        ...makeTextNode(`${i+1}. ${r}`, { style: 'essayAnswer', margin: [0,2,0,2] })
      }));
      responsesBlock = { 
        stack: [ 
          makeTextNode('Sample responses', { style: 'smallHeader', margin: [0,8,0,4] }), 
          ...sample
        ] 
      };
    }

    return {
      margin: [0, 0, 0, 6],
      stack: [
        {
          table: {
            widths: ['*'],
            body: [
              [
                {
                  stack: [
                    { 
                      columns: [ 
                        { width: '*', stack: qTitleParts }, 
                        { width: 'auto', text: `ID: ${q.id || index}`, style: 'meta', alignment: 'right' } 
                      ] 
                    },
                    ...(summary.length ? summary : []),
                    distTable ? distTable : { text: '' },
                    responsesBlock ? responsesBlock : { text: '' }
                  ],
                  margin: [8,6,8,6]
                }
              ]
            ]
          },
          layout: { hLineWidth:()=>0, vLineWidth:()=>0 }
        }
      ]
    };
  }

  function buildSummaryTable(questions, numParticipants) {
    const header = [
      makeTextNode('Question', { style: 'tableHeader' }), 
      makeTextNode('Average', { style: 'tableHeader' }), 
      makeTextNode('Participants', { style: 'tableHeader' })
    ];
    const body = [header];
    questions.forEach((q,i)=> body.push([ 
      makeTextNode(`Q${i+1} ${ (q.english || q.arabic || '').toString().slice(0,60) }`), 
      makeTextNode(q.average !== undefined && q.average !== null ? roundDecimal(q.average, 2) : ''), 
      makeTextNode(q.n_participants ?? numParticipants ?? '')
    ]));
    return { 
      style: 'summaryTable', 
      table: { widths: ['*',80,80], body }, 
      layout: { 
        fillColor:(r)=> r===0? '#f5f9ff':null, 
        hLineWidth:()=>0.5, 
        vLineWidth:()=>0, 
        hLineColor:()=> '#e6eef7' 
      }, 
      margin: [0,6,0,12] 
    };
  }

  // --- main generateReport ---
  async function generateReport(opts = {}) {
    await ensurePdfMake({ vfsUrl: opts.vfsUrl, customVfsObj: opts.customVfsObj, almarai: opts.almarai });

    const evaluationTitle = opts.evaluationTitle || 'تقرير التقييم';
    const reportName = opts.reportName || '';
    const numParticipants = opts.numParticipants || 0;
    const showAverage = (opts.showAverage === undefined) ? true : !!opts.showAverage;
    const questions = Array.isArray(opts.questions) ? opts.questions : [];
    const author = opts.author || '';

    const docDefinition = {
      pageSize: 'A4',
      pageMargins: [40, 60, 40, 60],
      defaultStyle: { 
        fontSize: 10,
        font: getFont() // Set default font
      },
      styles: {
        address: { fontSize: 12, bold: true, color: '#d97757' },
        title: { fontSize: 16, bold: true, color: '#d97757' },
        subtitle: { fontSize: 11, color: '#666666' },
        meta: { fontSize: 9, color: '#666666' },
        smallMeta: { fontSize: 9, color: '#666666' },
        qBadge: { fillColor: '#813e28ff', color: 'white', bold: true, fontSize: 9, alignment: 'center', margin: [4,2,4,2] },
        qTitle: { fontSize: 12, bold: true },
        qSubtitle: { fontSize: 10, italics: true, color: '#666666' },
        smallHeader: { fontSize: 10, bold: true, color: '#d97757' },
        essayAnswer: { fontSize: 9 },
        tableHeader: { bold: true, fontSize: 10, color: '#d97757' },
        cardTable: { margin: [0,6,0,6] },
        summaryTable: { margin: [0,12,0,12] }
      },
      header: (currentPage, pageCount, pageSize) => ({
        margin: [40, 16, 40, 0],
        stack: [
          buildHeader({ evaluationTitle, reportName, numParticipants }),
          { canvas: [{ type: 'rect', x: 0, y: 6, w: pageSize.width - 80, h: 1, color: '#e6eef7' }] }
        ]
      }),
      footer: (currentPage, pageCount) => ({ 
        columns: [ 
          { text: author?`${author}`:'', alignment: 'left', margin: [40,0], font: getFont() }, 
          { text: `Page ${currentPage} / ${pageCount}`, alignment: 'right', margin: [0,0,40,0] } 
        ], 
        fontSize: 9 
      }),
      content: []
    };

    // content
    docDefinition.content.push({ text: '', margin: [0,0,0,4] }); // spacer
    if (questions.length) {
      docDefinition.content.push({ text: 'Summary', style: 'smallHeader', margin: [0,6,0,6], font: getFont() });
      docDefinition.content.push(buildSummaryTable(questions, numParticipants));
    }
    questions.forEach((q, idx) => docDefinition.content.push(renderQuestionCard(q, idx + 1, numParticipants, showAverage)));
    docDefinition.content.push({ text: 'End of Report', style: 'smallHeader', margin: [0,12,0,0], alignment: 'center', font: getFont() });

    const pdfDocGenerator = window.pdfMake.createPdf(docDefinition);
    return {
      pdfDocGenerator,
      open: function() { pdfDocGenerator.open(); },
      download: function(fileName = reportName || 'report.pdf') { pdfDocGenerator.download(fileName); },
      getDataUrl: function() { return new Promise((res, rej) => pdfDocGenerator.getDataUrl(d => d ? res(d) : rej(new Error('no dataurl')))); },
      getBase64: function() { return new Promise((res, rej) => pdfDocGenerator.getBase64(b => b ? res(b) : rej(new Error('no base64')))); }
    };
  }

  // export
  root.PDFReportGenerator = {
    generateReport,
    _internal: { safeNum, hasArabicText, ADDRESSES: ADDRESSES.slice() }
  };

})(window);