export default  function humanFileSize(bytes) {
    let thresh = 1024;

    if(bytes < thresh) {
        return bytes + ' B';
    }

    let units = ['kB','MB','GB','TB','PB','EB','ZB','YB'];
    let u = -1;

    do {
        bytes /= thresh;
        ++u;
    } while(bytes >= thresh);

    return bytes.toFixed(1)+' '+units[u];
};
