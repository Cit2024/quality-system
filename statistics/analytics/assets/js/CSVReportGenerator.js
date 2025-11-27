// /statistics/analytics/assets/js/CSVReportGenerator.js

// CSV Report Generator
function generateCSVReport(assessmentTitle, participants, questions) {
    // Create CSV content with proper BOM for Arabic
    let csvContent = "\uFEFF"; // UTF-8 BOM
    
    // Header Section
    csvContent += ` عنوان التقييم:, تقييم${assessmentTitle}\r\n`;
    csvContent += `مشاركون:,${participants}\r\n\r\n`;

    // Questions Section
    questions.forEach((question, index) => {
        csvContent += `نوع السؤال:,${question.type}\r\n`;
        csvContent += `نص السؤال:,${question.question}\r\n`;

        switch(question.type) {
            case 'evaluation':
                const safeDistribution = question.distribution || {1:0, 2:0, 3:0, 4:0, 5:0};
                const safeAverage = question.total > 0 
                    ? question.average.toFixed(1)
                    : 'N/A (No responses)';
                
                csvContent += `متوسط التقييم:,${safeAverage}\r\n`;
                csvContent += "توزيع التصنيف:\r\n";
                Object.entries(safeDistribution).forEach(([rating, count]) => {
                    csvContent += `${rating},${count}\r\n`;
                });
                break;
                
            case 'essay':
                csvContent += `\r\n`;
                csvContent += `الإجابات:\r\n`;
                if (question.responses.length === 0) {
                    csvContent += `لا توجد إجابات\r\n`;
                } else {
                    question.responses.forEach((response, idx) => {
                        csvContent += `الإجابة ${idx + 1}:\r\n`;
                        csvContent += `النص:,${response.content}\r\n`;
                        csvContent += `التاريخ:,${response.timestamp}\r\n`;
                        
                        // Handle metadata safely
                        const metadata = response.metadata || [];
                        if (Array.isArray(metadata) && metadata.length > 0) {
                            metadata.forEach(meta => {
                                if (meta?.label && meta?.value !== undefined) {
                                    csvContent += `${meta.label}:,${meta.value}\r\n`;
                                }
                            });
                        }
                        
                        csvContent += "\r\n";
                    });
                }
                break;

            case 'multiple_choice':
                // Ensure choices is always an object
                const safeChoices = question.choices || {};
                const total = question.total || 1; // Avoid division by zero
            
                Object.entries(safeChoices).forEach(([choice, count]) => {
                    const percentage = total > 0 
                        ? ((count / total) * 100).toFixed(1)
                        : "0.0";
                    csvContent += `${choice},${count} (${percentage}%)\r\n`;
                });
                break;

            case 'true_false':
                csvContent += `True:,${question.choices.true}\r\n`;
                csvContent += `False:,${question.choices.false}\r\n`;
                break;
        }

        csvContent += "\r\n";
    });

    // Return as Uint8Array instead of Blob
    const encoder = new TextEncoder();
    return encoder.encode(csvContent);
}