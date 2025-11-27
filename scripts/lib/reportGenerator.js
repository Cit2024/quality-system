// reportGenerator.js - HTML2PDF Version
(function initReportGenerator() {
  if (window.reportGeneratorInitialized) return;
  window.reportGeneratorInitialized = true;

  // Configuration
  const CONFIG = {
    html2pdfUrl:
      "https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js",
    arabicFont: "https://fonts.googleapis.com/css2?family=Amiri&display=swap",
    primaryColor: "rgb(255, 99, 3)",
    header: ["وزارة الصناعة و المعادن", "كلية التنقية الصناعية"],
    pageMargin: 15,
  };

  // Load dependencies
  function loadDependencies() {
    return new Promise((resolve, reject) => {
      if (window.html2pdf) return resolve();

      // Load Arabic font
      const fontLink = document.createElement("link");
      fontLink.href = CONFIG.arabicFont;
      fontLink.rel = "stylesheet";
      document.head.appendChild(fontLink);

      // Load html2pdf.js
      const script = document.createElement("script");
      script.src = CONFIG.html2pdfUrl;
      script.onload = resolve;
      script.onerror = () => reject(new Error("فشل تحميل مكتبة PDF"));
      document.head.appendChild(script);
    });
  }

  // Generate CSS styles
  function getStyles() {
    return `
        <style>
          :root {
            --primary-color: ${CONFIG.primaryColor};
            --page-width: 210mm;
          }
  
          body {
            direction: rtl;
            font-family: 'Amiri', sans-serif;
            margin: ${CONFIG.pageMargin}mm;
            line-height: 1.6;
          }
  
          .header {
            background: ${CONFIG.primaryColor};
            color: white;
            padding: 10mm 0;
            text-align: center;
            margin-bottom: 15mm;
          }
  
          .metadata {
            margin-bottom: 10mm;
            border-bottom: 2px solid ${CONFIG.primaryColor};
            padding-bottom: 5mm;
          }
  
          table {
            width: 100%;
            border-collapse: collapse;
            margin: 5mm 0;
            page-break-inside: avoid;
          }
  
          th {
            background: ${CONFIG.primaryColor};
            color: white;
            padding: 2mm;
            border: 1px solid darken(${CONFIG.primaryColor}, 10%);
          }
  
          td {
            padding: 2mm;
            border: 1px solid #ddd;
            text-align: right;
          }
  
          .progress-bar {
            background: #eee;
            height: 4mm;
            border-radius: 2mm;
            overflow: hidden;
          }
  
          .progress-fill {
            height: 100%;
            background: ${CONFIG.primaryColor};
            transition: width 0.3s ease;
          }
  
          .footer {
            position: static;
            padding: 3mm;
            font-size: 0.8em;
            color: #666;
            border-top: 1px solid #ddd;
            background: white;
          }
  
          .page-number {
            float: left;
          }
  
          .creation-date {
            float: right;
          }
  
          .summary-page {
            page-break-before: always;
            padding-top: 20mm;
          }
        </style>
      `;
  }

  // Generate progress bar
  function generateProgressBar(rating, max = 5) {
    const percentage = (rating / max) * 100;
    return `
        <div class="progress-bar">
          <div class="progress-fill" style="width: ${percentage}%"></div>
        </div>
        <span>${rating.toFixed(2)}/5</span>
      `;
  }

  // Generate table rows
  function generateTableRows(questions, processor) {
    return questions
      .map(
        (q) => `
        <tr>
          ${processor(q)
            .map((cell) => `<td>${cell}</td>`)
            .join("")}
        </tr>
      `
      )
      .join("");
  }

  // Main report generation function
  window.generateReport = async function (
    evaluationType,
    reportName,
    numParticipants,
    showAverage,
    questions
  ) {
    try {
      // Input validation
      if (!evaluationType?.trim() || !reportName?.trim())
        throw new Error("المدخلات غير صالحة");
      if (!Number.isInteger(numParticipants) || numParticipants < 0)
        throw new Error("عدد المشاركين غير صالح");

      await loadDependencies();

      // Create container
      const container = document.createElement("div");
      container.id = "pdf-container";
      container.style.visibility = "hidden";
      document.body.appendChild(container);

      // Generate content
      container.innerHTML = `
          ${getStyles()}
          <div class="header">
            ${CONFIG.header.map((line) => `<h1>${line}</h1>`).join("")}
          </div>
  
          <div class="metadata">
            <p>نوع التقييم: ${evaluationType}</p>
            <p>اسم التقرير: ${reportName}</p>
            <p>عدد المشاركين: ${numParticipants}</p>
          </div>
  
          ${generateTables(questions, numParticipants, showAverage)}
  
          <div class="footer">
            <span class="creation-date">${new Date().toLocaleString()}</span>
            <span class="page-number"></span>
          </div>
        `;

      // Configure html2pdf
      const options = {
        filename: `${reportName}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: { 
            scale: 3, 
            useCORS: true,
            allowTaint: true
                },
        jsPDF: {
          unit: "mm",
          format: "a4",
          orientation: "portrait",
        },
        pagebreak: { mode: "css" },
      };

      // Generate Blob PDF
     const pdfArrayBuffer = await html2pdf().set(options).from(container).outputPdf('arraybuffer');
     const pdfBlob = new Blob([pdfArrayBuffer], { type: 'application/pdf' });
  
      // Add cleanup and return
      document.body.removeChild(container);
      return pdfBlob;
    } catch (error) {
      console.error("Report Generation Error:", error);
      throw new Error(`فشل إنشاء التقرير: ${error.message}`);
    }
  };

  // Generate all tables
  function generateTables(questions, numParticipants, showAverage) {
    return `
        ${generateEvaluationTable(questions)}
        ${generateTrueFalseTable(questions, numParticipants)}
        ${generateMultipleChoiceTable(questions)}
        ${generateEssayTable(questions)}
        ${showAverage ? generateSummary(questions, numParticipants) : ""}
      `;
  }

  function generateEvaluationTable(questions) {
    const evalQuestions = questions.filter((q) => q.type === "evaluation");
    if (evalQuestions.length === 0) return `<p>لا توجد أسئلة تقييم متاحة</p>`;

    return `
        <table>
          <thead>
            <tr><th>السؤال</th><th>التقييم</th><th>التمثيل البصري</th></tr>
          </thead>
          <tbody>
            ${generateTableRows(evalQuestions, (q) => {
              const answers = q.answer || [];
              const avg = answers.length
                ? answers.reduce((a, b) => a + b, 0) / answers.length
                : 0;
              return [q.text, avg.toFixed(2), generateProgressBar(avg)];
            })}
          </tbody>
        </table>
      `;
  }

  function generateTrueFalseTable(questions, numParticipants) {
    const tfQuestions = questions.filter((q) => q.type === "trueFalse");
    if (tfQuestions.length === 0) return "";

    return `
        <table>
          <thead>
            <tr><th>السؤال</th><th>نعم (%)</th><th>لا (%)</th></tr>
          </thead>
          <tbody>
            ${generateTableRows(tfQuestions, (q) => {
              const answers = q.answer || [];
              const total = numParticipants || 1;
              const trues = answers.filter((a) => a === true).length;
              return [
                q.text,
                `${((trues / total) * 100).toFixed(1)}%`,
                `${(((total - trues) / total) * 100).toFixed(1)}%`,
              ];
            })}
          </tbody>
        </table>
      `;
  }

  function generateMultipleChoiceTable(questions) {
    const mcQuestions = questions.filter((q) => q.type === "multipleChoice");
    if (mcQuestions.length === 0) return "";

    return `
        <table>
          <thead>
            <tr><th>السؤال</th><th>الخيارات</th></tr>
          </thead>
          <tbody>
            ${generateTableRows(mcQuestions, (q) => [
              q.text,
              Object.entries(q.answer)
                .map(([k, v]) => `<div>${k}: ${v}</div>`)
                .join(""),
            ])}
          </tbody>
        </table>
      `;
  }

  function generateEssayTable(questions) {
    const essayQuestions = questions.filter((q) => q.type === "essay");
    if (essayQuestions.length === 0) return "";

    return `
        <table>
          <thead>
            <tr><th>السؤال</th><th>الإجابات</th></tr>
          </thead>
          <tbody>
            ${generateTableRows(essayQuestions, (q) => [
              q.text,
              Array.isArray(q.answer)
                ? q.answer.join("<br><br>")
                : String(q.answer),
            ])}
          </tbody>
        </table>
      `;
  }

  function generateSummary(questions, numParticipants) {
    const evalQuestions = questions.filter((q) => q.type === "evaluation");
    const allRatings = evalQuestions.flatMap((q) => q.answer || []);

    const summaryData = {
      totalRatings: allRatings.length,
      overallAvg: allRatings.length
        ? (allRatings.reduce((a, b) => a + b, 0) / allRatings.length).toFixed(2)
        : "N/A",
      participationRate:
        numParticipants > 0
          ? `${((allRatings.length / numParticipants) * 100).toFixed(1)}%`
          : "0%",
    };

    return `
        <div class="summary-page">
          <h2>الملخص العام</h2>
          <ul>
            <li>المعدل العام: ${summaryData.overallAvg}</li>
            <li>إجمالي التقييمات: ${summaryData.totalRatings}</li>
            <li>نسبة المشاركة: ${summaryData.participationRate}</li>
          </ul>
        </div>
      `;
  }
})();
