import Vue from 'vue'
import vueDropzone from "./dropzone"
import GalleryLink from './gallery-link.vue'

const app = new Vue({
  el: '#app',
  components: {
    vueDropzone,
    GalleryLink
  },
  data() {
    return {
      shareIt: false,
      session: window._session,
      expired: 0,
      files: [],
      zoom: false,
      awss3: {
        signingURL: '/signature',
        headers: {},
        params : {
          csrf: window._csrf
        },
        sendFileToServer: true,
      },
      dropOptions: {
        url: "/upload",
        addRemoveLinks: false,
        acceptedFiles: 'image/*',
        paramName: 'files',
        createImageThumbnails: true,
        dictDefaultMessage: '<div class="upload-icon"><i class="icon ion-upload ion-2x"></i></div><div>Click to choose or drag & drop files anywhere</div>'
      }
    }
  },
  methods: {
    sendingEvent (file, xhr, formData) {
      formData.append('session', this.session);
      formData.append('expired', this.expired);
      formData.append('csrf', window._csrf);
      formData.delete('files');
    },
    uploadComplete() {
      let slug = this.files[0].filename;
      window.location.replace(`${this.session}/${slug}`);
    },
    uploadSuccess(file, res) {
      let fileObj = res;
      this.files.push(fileObj);
    },
    s3UploadError(error) {
      console.log(error)
    },
    s3UploadSuccess(location) {
      console.log(location)
    },
    async deleteFile(id){
      let c = confirm('hapus gambar ini?')
      if(c == true){
        let xmlhttp = new XMLHttpRequest();   // new HttpRequest instance 
        let theUrl = `/destroy/${id}`;
        await xmlhttp.open("POST", theUrl);
        await xmlhttp.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
        await xmlhttp.send();
        window.location.replace('/');
      }
    },
    copy(target) {
      let i = this.$refs[target];
      i.select();
      try {
        var successful = document.execCommand('copy');
        var msg = successful ? 'successful' : 'unsuccessful';
        alert('text was copied ' + msg);
      } catch (err) {
        alert('Oops, unable to copy');
      }
    },
    drop(e) {
      e.preventDefault();
      let dropzone = this.$refs.dropHere.dropzone
      if (e.dataTransfer && e.dataTransfer.files.length) {
        dropzone.drop({ dataTransfer: e.dataTransfer })
      }
    },
    allowDrop(e) {
      e.preventDefault();
    }
  }
});