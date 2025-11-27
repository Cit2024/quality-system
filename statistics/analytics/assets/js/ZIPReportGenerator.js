// analytics/assets/js/ZIPReportGenerator.js

window.createZipBlob = async function(files) {
    const zip = new JSZip();
    
    const addFilesToZip = (zipInstance, items) => {
        items.forEach(item => {
            if (item.isDirectory) {
                const folder = zipInstance.folder(item.name);
                addFilesToZip(folder, item.children); // معالجة متداخلة
            } else {
                zipInstance.file(item.name, item.content);
            }
        });
    };
    
    addFilesToZip(zip, files); // Call the new function
    
    return zip.generateAsync({
        type: "blob",
        compression: "DEFLATE",
        compressionOptions: { level: 9 },
        platform: 'UNIX',
        encodeFileName: name => encodeURIComponent(name)
    });
};