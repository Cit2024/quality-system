// /statistics/analytics/assets/js/ExcelReportGenerator.js - FIXED VERSION

/**
 * Enhanced Excel Report Generator with:
 * - Auto column widths
 * - Charts for evaluation and multiple choice questions
 * - Summary statistics sheet
 * - Professional styling
 * - RTL support for Arabic
 * - FIXED: Question titles now display properly
 */
function generateExcelReport(assessmentTitle, participants, questions) {
    const wb = XLSX.utils.book_new();
    
    // Set workbook properties
    wb.Props = {
        Title: assessmentTitle,
        Subject: "Evaluation Report",
        Author: "إدارة التوثيق والمعلومات والإعلام",
        CreatedDate: new Date()
    };
    
    // Generate Summary Sheet
    const summarySheet = generateSummarySheet(assessmentTitle, participants, questions);
    XLSX.utils.book_append_sheet(wb, summarySheet, "ملخص التقييم");
    
    // Generate Detailed Questions Sheet
    const detailsSheet = generateDetailsSheet(assessmentTitle, participants, questions);
    XLSX.utils.book_append_sheet(wb, detailsSheet, "تفاصيل الأسئلة");
    
    // Generate Charts Sheet (if applicable)
    const chartsSheet = generateChartsDataSheet(questions);
    if (chartsSheet) {
        XLSX.utils.book_append_sheet(wb, chartsSheet, "بيانات الرسوم البيانية");
    }
    
    // Generate individual sheets for essay questions
    const essayQuestions = questions.filter(q => q.type === 'essay');
    if (essayQuestions.length > 0) {
        essayQuestions.forEach((q, index) => {
            const essaySheet = generateEssaySheet(q, index);
            const sheetName = `إجابات ${index + 1}`.substring(0, 31); // Excel limit
            XLSX.utils.book_append_sheet(wb, essaySheet, sheetName);
        });
    }
    
    return XLSX.write(wb, {type: 'array', bookType: 'xlsx'});
}

/**
 * Helper function to extract question title
 */
function getQuestionTitle(q) {
    // Try to get the full question text from various possible properties
    if (q.question) return q.question;
    if (q.arabic) return q.arabic;
    if (q.english) return q.english;
    if (q.TitleQuestion) return q.TitleQuestion;
    return 'Untitled Question';
}

/**
 * Generate Summary Sheet with overview statistics
 */
function generateSummarySheet(title, participants, questions) {
    const data = [];
    
    // Header section
    data.push(['تقرير التقييم الشامل']);
    data.push([]);
    data.push(['عنوان التقييم:', title]);
    data.push(['عدد المشاركين:', participants]);
    data.push(['تاريخ التقرير:', new Date().toLocaleDateString()]);
    data.push(['إجمالي الأسئلة:', questions.length]);
    data.push([]);
    
    // Question type breakdown
    data.push(['توزيع أنواع الأسئلة']);
    data.push(['نوع السؤال', 'العدد', 'النسبة المئوية']);
    
    const questionTypes = {};
    questions.forEach(q => {
        questionTypes[q.type] = (questionTypes[q.type] || 0) + 1;
    });
    
    const typeLabels = {
        'evaluation': 'أسئلة التقييم',
        'essay': 'أسئلة المقال',
        'multiple_choice': 'أسئلة الاختيار من متعدد',
        'true_false': 'أسئلة صح وخطأ'
    };
    
    Object.entries(questionTypes).forEach(([type, count]) => {
        const percentage = ((count / questions.length) * 100).toFixed(1);
        data.push([typeLabels[type] || type, count, `${percentage}%`]);
    });
    
    data.push([]);
    
    // Statistics for evaluation questions
    const evaluationQuestions = questions.filter(q => q.type === 'evaluation');
    if (evaluationQuestions.length > 0) {
        data.push(['إحصائيات أسئلة التقييم']);
        data.push(['المقياس', 'القيمة']);
        
        const averages = evaluationQuestions
            .map(q => q.average)
            .filter(a => a !== undefined && a !== null);
        
        if (averages.length > 0) {
            const overallAverage = averages.reduce((sum, avg) => sum + avg, 0) / averages.length;
            const maxAverage = Math.max(...averages);
            const minAverage = Math.min(...averages);
            
            data.push(['المتوسط العام', overallAverage.toFixed(2)]);
            data.push(['أعلى متوسط', maxAverage.toFixed(2)]);
            data.push(['أدنى متوسط', minAverage.toFixed(2)]);
        }
        
        data.push([]);
    }
    
    // Question summary table - FIXED: Now includes question titles
    data.push(['ملخص الأسئلة']);
    data.push(['رقم السؤال', 'نص السؤال', 'نوع السؤال', 'المتوسط/الإجابات', 'عدد المشاركين']);
    
    questions.forEach((q, index) => {
        let summary = '';
        if (q.type === 'evaluation') {
            summary = q.average ? q.average.toFixed(2) : 'N/A';
        } else if (q.type === 'essay') {
            summary = `${q.responses?.length || 0} إجابة`;
        } else if (q.type === 'multiple_choice' || q.type === 'true_false') {
            summary = `${Object.keys(q.choices || {}).length} خيارات`;
        }
        
        // Get the question title
        const questionTitle = getQuestionTitle(q);
        // Truncate if too long for summary
        const truncatedTitle = questionTitle.length > 100 
            ? questionTitle.substring(0, 100) + '...' 
            : questionTitle;
        
        data.push([
            `Q${index + 1}`,
            truncatedTitle,  // ADDED: Question title
            typeLabels[q.type] || q.type,
            summary,
            q.total || 0
        ]);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Apply styling
    const range = XLSX.utils.decode_range(ws['!ref']);
    
    // Set column widths - UPDATED: Added column for question title
    ws['!cols'] = [
        {wch: 15}, // Column A - Question number
        {wch: 60}, // Column B - Question title (NEW)
        {wch: 25}, // Column C - Question type
        {wch: 20}, // Column D - Average/Answers
        {wch: 20}  // Column E - Participants
    ];
    
    // Style header cells
    for (let R = 0; R <= range.e.r; ++R) {
        for (let C = 0; C <= range.e.c; ++C) {
            const cell_address = XLSX.utils.encode_cell({r: R, c: C});
            if (!ws[cell_address]) continue;
            
            // Initialize style object
            ws[cell_address].s = ws[cell_address].s || {};
            
            // Style for title row
            if (R === 0) {
                ws[cell_address].s = {
                    font: {bold: true, sz: 16, color: {rgb: "FFFFFF"}},
                    fill: {fgColor: {rgb: "1F4E78"}},
                    alignment: {horizontal: "center", vertical: "center"}
                };
            }
            // Style for section headers
            else if (data[R] && (
                data[R][0] === 'توزيع أنواع الأسئلة' ||
                data[R][0] === 'إحصائيات أسئلة التقييم' ||
                data[R][0] === 'ملخص الأسئلة'
            )) {
                ws[cell_address].s = {
                    font: {bold: true, sz: 12, color: {rgb: "FFFFFF"}},
                    fill: {fgColor: {rgb: "4472C4"}},
                    alignment: {horizontal: "right"}
                };
            }
            // Style for table headers
            else if (R > 0 && data[R-1] && (
                data[R-1][0] === 'توزيع أنواع الأسئلة' ||
                data[R-1][0] === 'إحصائيات أسئلة التقييم' ||
                data[R-1][0] === 'ملخص الأسئلة'
            )) {
                ws[cell_address].s = {
                    font: {bold: true, sz: 11},
                    fill: {fgColor: {rgb: "D9E1F2"}},
                    alignment: {horizontal: "center", vertical: "center"}
                };
            }
        }
    }
    
    return ws;
}

/**
 * Generate Detailed Questions Sheet
 */
function generateDetailsSheet(title, participants, questions) {
    const data = [];
    
    // Header
    data.push(['عنوان التقييم:', title]);
    data.push(['مشاركون:', participants]);
    data.push([]);
    data.push(['نوع السؤال', 'نص السؤال']);
    data.push([]);
    
    questions.forEach((question, qIndex) => {
        // Get full question title
        const fullTitle = getQuestionTitle(question);
        
        // Question header
        data.push([`السؤال ${qIndex + 1}`, fullTitle]);
        data.push(['النوع:', question.type]);
        data.push([]);
        
        // Question-specific data
        switch(question.type) {
            case 'evaluation':
                const avgValue = question.total > 0 && question.average
                    ? question.average.toFixed(1)
                    : 'N/A';
                
                data.push(['متوسط التقييم:', avgValue]);
                data.push(['إجمالي المشاركين:', question.total || 0]);
                data.push([]);
                data.push(['التقييم', 'العدد', 'النسبة المئوية']);
                
                // Handle both object and array distribution formats
                const distribution = question.distribution;
                const distEntries = Array.isArray(distribution)
                    ? distribution.map(d => [d.option, d.count])
                    : Object.entries(distribution || {});
                
                distEntries.forEach(([rating, count]) => {
                    const percentage = question.total > 0
                        ? ((count / question.total) * 100).toFixed(1)
                        : '0.0';
                    data.push([rating, count, `${percentage}%`]);
                });
                break;
                
            case 'essay':
                data.push(['عدد الإجابات:', question.responses?.length || 0]);
                data.push([]);
                
                if (question.responses && question.responses.length > 0) {
                    data.push(['رقم الإجابة', 'المحتوى', 'التاريخ']);
                    
                    question.responses.slice(0, 5).forEach((response, idx) => {
                        const content = typeof response === 'string' 
                            ? response 
                            : (response.content || '');
                        const timestamp = typeof response === 'object' 
                            ? (response.timestamp || '') 
                            : '';
                        
                        // Truncate long responses
                        const truncated = content.length > 200 
                            ? content.substring(0, 200) + '...' 
                            : content;
                        
                        data.push([idx + 1, truncated, timestamp]);
                    });
                    
                    if (question.responses.length > 5) {
                        data.push([`... وإجابات أخرى (${question.responses.length - 5})`]);
                    }
                }
                break;
                
            case 'multiple_choice':
                const total = question.total || 1;
                data.push(['الخيار', 'العدد', 'النسبة المئوية']);
                
                Object.entries(question.choices || {}).forEach(([choice, count]) => {
                    const percentage = ((count / total) * 100).toFixed(1);
                    data.push([choice, count, `${percentage}%`]);
                });
                break;
                
            case 'true_false':
                data.push(['الخيار', 'العدد', 'النسبة المئوية']);
                const tfTotal = (question.choices?.true || 0) + (question.choices?.false || 0);
                
                const truePercent = tfTotal > 0 
                    ? ((question.choices.true / tfTotal) * 100).toFixed(1) 
                    : '0.0';
                const falsePercent = tfTotal > 0 
                    ? ((question.choices.false / tfTotal) * 100).toFixed(1) 
                    : '0.0';
                
                data.push(['صح / True', question.choices?.true || 0, `${truePercent}%`]);
                data.push(['خطأ / False', question.choices?.false || 0, `${falsePercent}%`]);
                break;
        }
        
        // Separator between questions
        data.push([]);
        data.push([]);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Auto-size columns
    const colWidths = calculateColumnWidths(data);
    ws['!cols'] = colWidths;
    
    return ws;
}

/**
 * Generate Charts Data Sheet
 */
function generateChartsDataSheet(questions) {
    const chartableQuestions = questions.filter(q => 
        q.type === 'evaluation' || q.type === 'multiple_choice' || q.type === 'true_false'
    );
    
    if (chartableQuestions.length === 0) return null;
    
    const data = [];
    data.push(['بيانات الرسوم البيانية']);
    data.push([]);
    
    chartableQuestions.forEach((q, index) => {
        const questionTitle = getQuestionTitle(q);
        data.push([`السؤال ${index + 1}: ${questionTitle.substring(0, 50)}`]);
        
        if (q.type === 'evaluation') {
            data.push(['التقييم', 'العدد']);
            const distribution = Array.isArray(q.distribution)
                ? q.distribution
                : Object.entries(q.distribution || {}).map(([k, v]) => ({option: k, count: v}));
            
            distribution.forEach(item => {
                const option = item.option || item[0];
                const count = item.count || item[1];
                data.push([option, count]);
            });
        } else if (q.type === 'multiple_choice') {
            data.push(['الخيار', 'العدد']);
            Object.entries(q.choices || {}).forEach(([choice, count]) => {
                data.push([choice, count]);
            });
        } else if (q.type === 'true_false') {
            data.push(['الخيار', 'العدد']);
            data.push(['صح', q.choices?.true || 0]);
            data.push(['خطأ', q.choices?.false || 0]);
        }
        
        data.push([]);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    ws['!cols'] = [{wch: 40}, {wch: 15}];
    
    return ws;
}

/**
 * Generate Essay Sheet
 */
function generateEssaySheet(question, index) {
    const data = [];
    
    const questionTitle = getQuestionTitle(question);
    
    data.push([`إجابات السؤال ${index + 1}`]);
    data.push(['السؤال:', questionTitle]);
    data.push(['عدد الإجابات:', question.responses?.length || 0]);
    data.push([]);
    data.push(['رقم', 'الإجابة', 'التاريخ', 'بيانات إضافية']);
    
    (question.responses || []).forEach((response, idx) => {
        const content = typeof response === 'string' 
            ? response 
            : (response.content || '');
        const timestamp = typeof response === 'object' 
            ? (response.timestamp || '') 
            : '';
        
        // Format metadata
        let metadataStr = '';
        if (typeof response === 'object' && Array.isArray(response.metadata)) {
            metadataStr = response.metadata
                .filter(m => m && m.label && m.value)
                .map(m => `${m.label}: ${m.value}`)
                .join('; ');
        }
        
        data.push([idx + 1, content, timestamp, metadataStr]);
    });
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    
    // Set column widths for essay responses
    ws['!cols'] = [
        {wch: 8},  // Number
        {wch: 80}, // Answer (wide for essay text)
        {wch: 20}, // Timestamp
        {wch: 30}  // Metadata
    ];
    
    // Enable text wrapping for answer column
    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let R = 5; R <= range.e.r; ++R) {
        const cell = ws[XLSX.utils.encode_cell({r: R, c: 1})];
        if (cell) {
            cell.s = {alignment: {wrapText: true, vertical: "top"}};
        }
    }
    
    return ws;
}

/**
 * Calculate optimal column widths based on content
 */
function calculateColumnWidths(data) {
    const colWidths = [];
    const maxCols = Math.max(...data.map(row => Array.isArray(row) ? row.length : 0));
    
    for (let col = 0; col < maxCols; col++) {
        let maxWidth = 10; // Minimum width
        
        for (let row = 0; row < data.length; row++) {
            if (data[row] && data[row][col] !== undefined) {
                const cellValue = String(data[row][col]);
                // Calculate width (approximate)
                const cellWidth = cellValue.length * 1.2;
                maxWidth = Math.max(maxWidth, Math.min(cellWidth, 80)); // Max 80 chars
            }
        }
        
        colWidths.push({wch: Math.ceil(maxWidth)});
    }
    
    return colWidths;
}

/**
 * Apply cell styling (for enhanced formatting)
 */
function applyCellStyle(ws, cell, style) {
    if (!ws[cell]) return;
    ws[cell].s = style;
}

// Export the function
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { generateExcelReport };
}