// analytics/assets/js/download-utilities.js - FIXED VERSION

(() => {
    'use strict';

    /** Report configuration constants */
    const REPORT_CONFIG = {
        COURSE: {
            fileNamePrefix: 'تقرير_المقرر',
            questionType: 'course_evaluation'
        },
        TEACHER: {
            fileNamePrefix: 'تقرير_المدرس',
            questionType: 'teacher_evaluation'
        },
        PROGRAM: {
            fileNamePrefix: 'تقرير_البرنامج',
            questionType: 'program_evaluation'
        },
        COURSE_BUNDLE: {
            fileNamePrefix: 'تقارير_المقررات',
            questionType: 'course_evaluation'
        },
        COURSE_HISTORY: {
            fileNamePrefix: 'تاريخ_المقرر',
            questionType: 'course_evaluation'
        },
        TEACHER_HISTORY: {
            fileNamePrefix: 'تاريخ_المدرس',
            questionType: 'teacher_evaluation'
        },
        PROGRAM_HISTORY: {
            fileNamePrefix: 'تاريخ_البرنامج',
            questionType: 'program_evaluation'
        }
    };

    /** UI Constants */
    const SELECTORS = {
        DOWNLOAD_BUTTON: '[data-report-type]',
        LOADING_CLASS: 'loading-spinner',
        ERROR_TOAST: 'error-toast',
        SUCCESS_TOAST: 'success-toast'
    };

    // FIXED: Correct dependency check
    const DEPENDENCIES = {
        JSZip: typeof JSZip !== 'undefined',
        PDFReportGenerator: typeof window.PDFReportGenerator !== 'undefined',
        CSVReportGenerator: typeof generateCSVReport !== 'undefined',
        ExcelReportGenerator: typeof generateExcelReport !== 'undefined'
    };

    // Main initialization
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Initializing download utilities...');
        console.log('Dependencies status:', DEPENDENCIES);
        
        // Don't block if PDF generator is missing - it's optional
        const criticalDeps = ['JSZip', 'CSVReportGenerator', 'ExcelReportGenerator'];
        const missingCritical = criticalDeps.filter(dep => !DEPENDENCIES[dep]);
        
        if (missingCritical.length > 0) {
            console.warn('Missing critical dependencies:', missingCritical);
            showErrorToast('بعض المكتبات المطلوبة غير متوفرة');
            
            // Try to load JSZip dynamically if missing
            if (!DEPENDENCIES.JSZip) {
                loadJSZip();
                return;
            }
        }
        
        if (!DEPENDENCIES.PDFReportGenerator) {
            console.warn('PDFReportGenerator not found - PDF reports will be skipped');
        }
        
        initializeReportDownloader();
        initializeHistoryDownloader(); 
        initializeCourseBundleDownloader();
    });

    /** Core functionality */
    function initializeReportDownloader(selector = SELECTORS.DOWNLOAD_BUTTON) {
        const buttons = document.querySelectorAll(selector);
        console.log(`Found ${buttons.length} report download buttons`);
        
        buttons.forEach(button => {
            button.addEventListener('click', handleReportDownload);
        });
    }

    function initializeCourseBundleDownloader() {
        const buttons = document.querySelectorAll('.download-all-courses');
        console.log(`Found ${buttons.length} course bundle download buttons`);
        
        buttons.forEach(button => {
            button.addEventListener('click', handleCourseBundleDownload);
        });
    }
    
    function initializeHistoryDownloader() {
        const buttons = document.querySelectorAll('.download-history');
        console.log(`Found ${buttons.length} history download buttons`);
        
        buttons.forEach(button => {
            button.addEventListener('click', handleHistoryDownload);
        });
    }

    /** Dynamically load JSZip */
    function loadJSZip() {
        console.log('Loading JSZip dynamically...');
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
        script.onload = () => {
            console.log('JSZip loaded successfully');
            DEPENDENCIES.JSZip = true;
            initializeReportDownloader();
            initializeHistoryDownloader();
            initializeCourseBundleDownloader();
        };
        script.onerror = () => {
            console.error('Failed to load JSZip');
            showErrorToast('فشل في تحميل مكتبة الضغط');
        };
        document.head.appendChild(script);
    }

    /** Event handlers */
    async function handleReportDownload(event) {
        event.preventDefault();
        const button = event.currentTarget;
        
        try {
            await withLoadingState(button, async () => {
                const config = getReportConfig(button.dataset.reportType);
                const { fileName, data } = parseReportData(button, config);
                
                console.log(`Generating report for: ${fileName}`);
                
                // Generate all report formats
                const reportFiles = await generateAllReportFormats(data, config, fileName);
                
                // Create ZIP file
                const zipBlob = await createZipBlob(reportFiles);
                
                // Trigger download
                triggerDownload(zipBlob, `${config.fileNamePrefix}_${fileName}`);
                showSuccessToast('تم تنزيل التقرير بنجاح');
            });
        } catch (error) {
            console.error('Report download error:', error);
            handleDownloadError(error);
        }
    }

    async function handleCourseBundleDownload(event) {
        event.preventDefault();
        const button = event.currentTarget;
        
        try {
            await withLoadingState(button, async () => {
                const courses = JSON.parse(button.dataset.allCourse || '[]');
                const config = REPORT_CONFIG.COURSE_BUNDLE;
                
                console.log(`Processing course bundle with ${courses.length} courses`);
                
                const zipFiles = await Promise.all(
                    courses.map(course => processCourseBundle(course, config))
                );
                
                const zipBlob = await createZipBlob(zipFiles.flat());
                triggerDownload(zipBlob, `${config.fileNamePrefix}_${sanitizeFilename(button.dataset.fileName)}`);
                
                showSuccessToast(`تم تنزيل ${courses.length} تقارير بنجاح`);
            });
        } catch (error) {
            console.error('Course bundle download error:', error);
            handleDownloadError(error);
        }
    }
    
    async function handleHistoryDownload(event) {
        event.preventDefault();
        const button = event.currentTarget;
    
        try {
            await withLoadingState(button, async () => {
                const reportType = button.dataset.reportType;
                const config = REPORT_CONFIG[`${reportType.toUpperCase()}_HISTORY`];
                const allSemesters = JSON.parse(button.dataset.allSemester || '[]');
                const basicInfo = JSON.parse(button.dataset.basicInformation || '{}');
                
                console.log(`Processing history for ${reportType} with ${allSemesters.length} semesters`);
                
                const entityName = sanitizeFilename(
                    reportType === 'teacher' 
                        ? basicInfo.name 
                        : basicInfo.MadaName || basicInfo.evaluator || 'untitled'
                );
    
                const zipFiles = await Promise.all(
                    allSemesters.map(semester => 
                        processSemesterBundle(semester, config, entityName, reportType)
                    )
                );
    
                const zipBlob = await createZipBlob(zipFiles.flat());
                triggerDownload(zipBlob, `${config.fileNamePrefix}_${entityName}`);
    
                showSuccessToast(`تم تنزيل ${allSemesters.length} فصول دراسية`);
            });
        } catch (error) {
            console.error('History download error:', error);
            handleDownloadError(error);
        }
    }

    /** Generate all report formats for a single report */
    async function generateAllReportFormats(data, config, fileName) {
        const assessmentTitle = getAssessmentTitle(data, config);
        const participants = data.stats?.participants || 0;
        const processedQuestions = processQuestions(data.questions, config.questionType);
        
        const files = [];
        
        // Generate CSV
        if (DEPENDENCIES.CSVReportGenerator) {
            try {
                // Use fullResponses for CSV if available, otherwise use responses
                const csvQuestions = processedQuestions.map(q => ({
                    ...q,
                    responses: q.fullResponses || q.responses || []
                }));
                const csvContent = generateCSVReport(assessmentTitle, participants, csvQuestions);
                files.push({
                    name: `${config.fileNamePrefix}_${fileName}.csv`,
                    content: new Blob([csvContent], { type: 'text/csv;charset=utf-8;' }),
                    isDirectory: false
                });
                console.log("CSV generation successful");
            } catch (error) {
                console.warn('CSV generation failed:', error);
            }
        }
        
        // Generate Excel
        if (DEPENDENCIES.ExcelReportGenerator) {
            try {
                // Use fullResponses for Excel if available
                const excelQuestions = processedQuestions.map(q => ({
                    ...q,
                    responses: q.fullResponses || q.responses || []
                }));
                const excelContent = generateExcelReport(assessmentTitle, participants, excelQuestions);
                files.push({
                    name: `${config.fileNamePrefix}_${fileName}.xlsx`,
                    content: new Blob([excelContent], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }),
                    isDirectory: false
                });
                console.log("Excel generation successful");
            } catch (error) {
                console.warn('Excel generation failed:', error);
            }
        }
        
        // Generate PDF - FIXED: Check for window.PDFReportGenerator
        if (DEPENDENCIES.PDFReportGenerator && window.PDFReportGenerator) {
            try {
                // PDF uses simple string array for responses
                const pdfQuestions = processedQuestions.map(q => {
                    const pdfQ = { ...q };
                    // Ensure responses is an array of strings for PDF
                    if (Array.isArray(q.responses)) {
                        pdfQ.responses = q.responses.map(r => 
                            typeof r === 'string' ? r : (r.content || String(r))
                        );
                    }
                    return pdfQ;
                });
                
                const pdfResult = await window.PDFReportGenerator.generateReport({
                    evaluationTitle: assessmentTitle,
                    reportName: fileName,
                    numParticipants: participants,
                    showAverage: true,
                    questions: pdfQuestions,
                    almarai: true,
                    author: 'إدارة التوثيق والمعلومات والإعلام'
                });
                
                const pdfBlob = await new Promise((resolve, reject) => {
                    pdfResult.pdfDocGenerator.getBlob(blob => {
                        blob ? resolve(blob) : reject(new Error('Failed to create PDF'));
                    });
                });
                
                files.push({
                    name: `${config.fileNamePrefix}_${fileName}.pdf`,
                    content: pdfBlob,
                    isDirectory: false
                });
                
                console.log("PDF generation successful:", `${config.fileNamePrefix}_${fileName}.pdf`);
            } catch (pdfError) {
                console.error('PDF generation failed:', pdfError);
                console.warn('Skipping PDF - continuing with other formats');
            }
        } else {
            console.warn('PDFReportGenerator not available - skipping PDF generation');
        }
        
        if (files.length === 0) {
            throw new Error('No report generators available');
        }
        
        return files;
    }

    /** Process semester bundle for history downloads */
    async function processSemesterBundle(semester, config, entityName, reportType) {
        const isProgramReport = reportType === 'program';
        const semesterName = sanitizeFilename(
            isProgramReport 
                ? `${semester.name_month} ${semester.number_month}`
                : semester.semester_name
        );
        
        const semesterFolder = {
            name: semesterName,
            isDirectory: true,
            children: []
        };
        
        // For teacher reports with nested courses
        const hasNestedCourses = reportType === 'teacher' && Array.isArray(semester.courses);
        
        if (hasNestedCourses && semester.courses?.length) {
            for (const course of semester.courses) {
                const courseData = {
                    basicInfo: { name: course.course_name },
                    stats: { participants: course.participants || 0 },
                    questions: course.questions || {}
                };
                
                const courseFiles = await generateAllReportFormats(
                    courseData, 
                    config, 
                    sanitizeFilename(course.course_name)
                );
                
                const courseFolder = {
                    name: sanitizeFilename(course.course_name),
                    isDirectory: true,
                    children: courseFiles
                };
                
                semesterFolder.children.push(courseFolder);
            }
        } else {
            const semesterData = {
                basicInfo: { name: entityName },
                stats: { participants: semester.participants || 0 },
                questions: semester.questions || {}
            };
            
            const semesterFiles = await generateAllReportFormats(
                semesterData,
                config,
                semesterName
            );
            
            semesterFolder.children.push(...semesterFiles);
        }
        
        return semesterFolder;
    }

    /** Helper to process individual courses for bundle download */
    async function processCourseBundle(course, config) {
        const courseData = {
            basicInfo: { name: course.name },
            stats: { participants: course.evaluation_count || 0 },
            questions: course.questions || {}
        };
        
        const courseFiles = await generateAllReportFormats(
            courseData,
            config,
            sanitizeFilename(course.name)
        );
        
        return [{
            name: sanitizeFilename(course.name),
            isDirectory: true,
            children: courseFiles
        }];
    }

    /** Utility functions */
    function getAssessmentTitle(data, config) {
        if (config === REPORT_CONFIG.PROGRAM) {
            return data.basicInfo?.evaluator || data.basicInfo?.program || 'برنامج غير معروف';
        }
        return data.basicInfo?.name || data.basicInfo?.MadaName || 'Untitled';
    }

    function getReportConfig(reportType) {
        const configMap = {
            course: REPORT_CONFIG.COURSE,
            teacher: REPORT_CONFIG.TEACHER,
            program: REPORT_CONFIG.PROGRAM,
            course_bundle: REPORT_CONFIG.COURSE_BUNDLE
        };

        if (!configMap[reportType?.toLowerCase()]) {
            throw new Error(`نوع التقرير غير صحيح: ${reportType}`);
        }

        return configMap[reportType];
    }

    function parseReportData(button, config) {
        try {
            const basicInfo = JSON.parse(button.dataset.basicInformation || '{}');
            const rawQuestions = JSON.parse(button.dataset.questions || '{}');
            const stats = JSON.parse(button.dataset.stats || '{}');

            return {
                fileName: sanitizeFilename(button.dataset.fileName || 'untitled'),
                data: {
                    basicInfo,
                    questions: rawQuestions,
                    stats
                }
            };
        } catch (error) {
            throw new Error(`فشل في تحليل البيانات: ${error.message}`);
        }
    }

    function processQuestions(questionsData, questionType) {
        if (!questionsData) return [];
        
        const processed = [];
        
        Object.values(questionsData).forEach(category => {
            if (category && typeof category === 'object') {
                Object.values(category).forEach(question => {
                    if (question && typeof question === 'object') {
                        processed.push(processSingleQuestion(question));
                    }
                });
            }
        });
        
        return processed.filter(q => q !== null);
    }

    function processSingleQuestion(question) {
        if (!question) return null;
        
        // Split bilingual text into Arabic and English
        const splitBilingualText = (text) => {
            if (!text) return { arabic: '', english: '' };
            
            const textStr = String(text);
            
            // Check if text contains both Arabic and English
            const hasArabic = /[\u0600-\u06FF]/.test(textStr);
            const hasEnglish = /[a-zA-Z]/.test(textStr);
            
            if (hasArabic && hasEnglish) {
                // Try to split on common patterns
                // Pattern 1: ". The " or ". the "
                let parts = textStr.split(/\.\s*The\s+/i);
                if (parts.length === 2) {
                    return {
                        arabic: parts[0].trim(),
                        english: 'The ' + parts[1].trim()
                    };
                }
                
                // Pattern 2: First English letter
                const firstEnglishIndex = textStr.search(/[A-Z]/);
                if (firstEnglishIndex > 0) {
                    return {
                        arabic: textStr.substring(0, firstEnglishIndex).trim(),
                        english: textStr.substring(firstEnglishIndex).trim()
                    };
                }
                
                // Fallback: put in arabic
                return { arabic: textStr, english: '' };
            } else if (hasArabic) {
                return { arabic: textStr, english: '' };
            } else {
                return { arabic: '', english: textStr };
            }
        };
        
        const bilingualTitle = splitBilingualText(question.Title);
        
        const processed = {
            id: question.ID,
            type: question.Type,
            question: question.Title,
            arabic: bilingualTitle.arabic,
            english: bilingualTitle.english,
            answers: question.Answers || [],
            total: question.Answers?.length || 0
        };
        
        // Add distribution for evaluation questions
        if (question.Type === 'evaluation' && question.distribution) {
            // Convert distribution object to array format for PDF
            const distributionArray = Object.entries(question.distribution).map(([rating, count]) => ({
                option: rating,
                count: count
            }));
            
            processed.distribution = distributionArray;
            processed.counts = question.distribution; // Keep original for other formats
            processed.average = calculateAverageRating(question.distribution, processed.total);
            processed.max_score = 5; // Assuming 5-point scale
        }
        
        // Add choices for multiple choice
        if (question.Type === 'multiple_choice') {
            const choices = arrayCountValues(question.Answers.map(a => a.value));
            processed.choices = choices;
            
            // Convert to distribution array for PDF
            processed.distribution = Object.entries(choices).map(([option, count]) => ({
                option: option,
                count: count
            }));
        }
        
        // Add true/false counts
        if (question.Type === 'true_false') {
            const counts = arrayCountValues(
                question.Answers.map(a => 
                    a.value === true ? 'true' : 
                    a.value === false ? 'false' : 
                    String(a.value).toLowerCase()
                )
            );
            processed.choices = {
                true: counts.true || counts.نعم || counts.yes || 0,
                false: counts.false || counts.لا || counts.no || 0
            };
            
            // Convert to distribution array for PDF
            processed.distribution = [
                { option: 'True / نعم', count: processed.choices.true },
                { option: 'False / لا', count: processed.choices.false }
            ];
        }
        
        // Add essay responses
        if (question.Type === 'essay') {
            processed.responses = question.Answers.map(answer => {
                const content = answer.value || '';
                // Return just the text content for PDF
                return String(content);
            });
            
            // Keep full response data for other formats
            processed.fullResponses = question.Answers.map(answer => ({
                content: answer.value || '',
                timestamp: answer.timestamp || '',
                metadata: answer.metadata || []
            }));
        }
        
        return processed;
    }

    function arrayCountValues(array) {
        return array.reduce((acc, value) => {
            const key = String(value);
            acc[key] = (acc[key] || 0) + 1;
            return acc;
        }, {});
    }

    function calculateAverageRating(distribution, total) {
        if (total <= 0) return 0;
        return Object.entries(distribution).reduce(
            (sum, [rating, count]) => sum + (Number(rating) * count),
            0
        ) / total;
    }

    function sanitizeFilename(name) {
        if (!name) return 'untitled';
        return name
            .replace(/[^\p{L}\p{N}\s_-]/gu, '')
            .replace(/\s+/g, '_')
            .trim();
    }

    async function createZipBlob(files) {
        if (!DEPENDENCIES.JSZip) {
            throw new Error('JSZip library is not available');
        }
        
        const zip = new JSZip();
        
        const addFilesToZip = (zipInstance, items) => {
            items.forEach(item => {
                if (item.isDirectory) {
                    const folder = zipInstance.folder(item.name);
                    addFilesToZip(folder, item.children);
                } else {
                    zipInstance.file(item.name, item.content);
                }
            });
        };
        
        addFilesToZip(zip, files);
        
        return zip.generateAsync({
            type: "blob",
            compression: "DEFLATE",
            compressionOptions: { level: 9 },
            platform: 'UNIX',
            encodeFileName: name => encodeURIComponent(name)
        });
    }

    function triggerDownload(blob, baseName) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `${baseName}_${new Date().toISOString().slice(0, 10)}.zip`;
        document.body.appendChild(link);
        link.click();
        
        setTimeout(() => {
            URL.revokeObjectURL(url);
            document.body.removeChild(link);
        }, 1000);
    }

    async function withLoadingState(button, operation) {
        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">جاري التحميل...</span></div> جاري التحميل...';
        
        try {
            await operation();
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    function handleDownloadError(error) {
        console.error('Download error:', error);
        showErrorToast(`فشل في التنزيل: ${error.message}`);
    }

    function showSuccessToast(message) {
        showToast(message, 'success');
    }

    function showErrorToast(message) {
        showToast(message, 'error');
    }

    function showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast ${type}-toast`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'error' ? '#f8d7da' : '#d1edff'};
            color: ${type === 'error' ? '#721c24' : '#004085'};
            border: 1px solid ${type === 'error' ? '#f5c6cb' : '#b8daff'};
            border-radius: 4px;
            z-index: 10000;
            font-family: 'DINRegular', sans-serif;
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

    /** Public API */
    window.downloadUtilities = {
        initializeReportDownloader,
        initializeCourseBundleDownloader,
        initializeHistoryDownloader,
        validateDependencies: () => DEPENDENCIES
    };
})();