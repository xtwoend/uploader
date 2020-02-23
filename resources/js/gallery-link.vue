<template>
	<div>
		<div class="field is-horizontal">
			<div class="field-label is-normal">
			    <label class="label">Kode untuk semua gambar:</label>
			</div>
			<div class="field-body">
				<div class="field has-addons">
					<div class="control is-expanded">
						<div class="select is-fullwidth">
					      <select name="country" @change="selected" v-model="type">
					        <option value="0">Tautan</option>
					        <option value="1">Alamat langsung</option>
					        <option value="2">Kode Markdown</option>
					        <option value="3">Gambar kecil untuk forum</option>
					        <option value="4">Gambar kecil untuk situs</option>
					        <option value="5">Hotlink untuk forum</option>
					        <option value="6">Hotlink untuk forum</option>
					      </select>
					    </div>
					</div>
					<div class="control">
						<a class="button is-info" @click="copy('code')">
					     	<i class="icon ion-ios-copy ion-2x"></i>
					    </a>
					</div>
				</div>
			</div>
		</div>
		<div class="field is-horizontal">
			<div class="field-label is-normal">
			    <label class="label"></label>
			</div>
			<div class="field-body">
				<div class="field">
					<div class="control is-expanded">
						<textarea v-model="text" ref="code" class="textarea" placeholder="Explain how we can help you"></textarea>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
export default {
  name: 'gallery-link',
  props: {
  	files: {
  		type: Array
  	},
  	host: {
  		type: String
  	}
  },
  data () {
    return {
    	type: 0,
    	text: ''
    }
  },
  mounted(){
  	this.text = this.buildText(this.type);
  },
  methods: {
  	selected () {
  		this.text = this.buildText(this.type);
  	},
  	buildText(type){
  		let link1='',link2='', link3='', link4='',
  				link5='',link6='', link7='';

  		this.files.forEach((e)=>{
  			link1 += `${this.host + e.filename}\n`
  			link2 += `http://${e.bucket}/${e.path}\n`
  			link3 += `[![${e.name}](http://${e.bucket}/${e.path} )](${this.host + e.filename})\n`
  			link4 += `[url=${this.host + e.filename}][img]http://${e.bucket}/thumb/${e.path}[/img][/url]\n`
  			link5 += `<a href='${this.host + e.filename}' target='_blank'><img src='http://${e.bucket}/thumb/${e.path}' border='0' alt='${e.name}'/></a>\n`
  			link6 += `[url=${this.host}][img]http://${e.bucket}/${e.path}[/img][/url][url=${this.host}]upload online[/url]\n`
  			link7 += `<a href='${this.host}' target='_blank'><img src='http://${e.bucket}/${e.path}' border='0' alt='23460.jpg'/></a><br /><a href='${this.host}'>upload online</a><br />\n`
  		})

  		let text = [link1, link2, link3, link4, link5, link6, link7];
  		return text[type];
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
  }
}
</script>

<style lang="css" scoped>
</style>