new Vue({
    el: '#app',
    data: {
        uploadTitle: '选择文件/Ctrl+V粘贴/拖拽到此上传',
        background: '#fff',
        showtype: 0,
        isBlock: false,
        alert: {
            type: 'success',
            msg: ''
        },
        beginTime: 0,
        loaded_size: 0,
        progress: 0,
        progress_tip: '',
        filename: '',
        uploadspeed: '',
        input: {
            csrf_token:'',
            show: true,
            ispwd: false,
            pwd: '',
            hash: '',
            name: '',
            size: 0
        },
    },
    mounted() {
        $(".colorful_loading_frame").hide();
        this.input.csrf_token = $("#csrf_token").val();

        var that = this;
        var fileInput = $("#fileInput");
        var elemetnNode="";
        //拖拽外部文件，进入目标元素触发
        fileInput.on("dragenter",function(e){
            elemetnNode=e.originalEvent.target;
            that.uploadTitle = '释放鼠标立即上传';
            that.background = '#ccc';
        });
        //拖拽外部文件，离开目标元素触发
        fileInput.on("dragleave",function(e){
            if(elemetnNode===e.originalEvent.target){
                that.uploadTitle = '选择文件/Ctrl+V粘贴/拖拽到此上传';
                that.background = '#fff';
            }
        });
        //拖拽外部文件，在目标元素上释放鼠标触发
        fileInput.on('dragover', false).on("drop",function(e){
            that.uploadTitle = '选择文件/Ctrl+V粘贴/拖拽到此上传';
            that.background = '#fff';
            var fs = e.originalEvent.dataTransfer.files;
            if(fs.length>0){
                that.uploadFile(fs[0])
            }
            return false;
        });

        document.addEventListener('paste', function(e) {
            var items = ((e.clipboardData || window.clipboardData).items) || [];
            var file = null;

            if (items && items.length) {
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('text/') === -1) {
                        file = items[i].getAsFile();
                        break;
                    }
                }
            }

            if (!file) {
                return;
            }
            that.uploadFile(file)
        });
    },
    methods: {
        show_msg(msg, type){
            type = type || 'success';
            this.alert.type = type;
            this.alert.msg = msg;
            this.showtype = 2;
            this.isBlock=false;
            $("#file").val('');
        },
        clickUpload(){
            if(this.isBlock) return;
            $("#file").trigger("click");
        },
        async selectFile(e){
            var total = e.target.files.length;
            if(total == 0) return;
            var fileObj = e.target.files[0];
            await this.uploadFile(fileObj)
        },
        async uploadFile(file){
            var that = this;
            if(this.isBlock) return;
            if(typeof forbid!=='undefined' && forbid){
                layer.alert('登录后才能上传文件！',{icon:0},function(){window.location.href='./login.php'});
                return;
            }
            if(upload_max_filesize != '' && parseInt(upload_max_filesize) > 0){
                if(file.size > parseInt(upload_max_filesize) * 1024 * 1024){
                    layer.alert('上传文件大小限制'+upload_max_filesize+'MB！');return;
                }
            }
            this.input.name = file.name;
            this.input.size = file.size;
            this.progress = 0;
            this.loaded_size = 0;
            this.showtype = 1;
            this.beginTime = new Date().getTime();

            let loading = layer.msg('正在准备上传...', {icon: 16,shade: 0.1,time: 0});
            await this.getFileHash(file).then(res =>{
                that.input.hash = res;
            })
            var result = {}
            await this.preUpload().then(res =>{
                result = res
            }, res => {
                layer.close(loading);
                that.show_msg(res, 'danger');
                throw Error();
            })
            layer.close(loading);

            this.filename = file.name + ' （'+this.size_format(file.size)+'）';
            this.isBlock = true;
            if(result.code == 1){
                this.progress = 100;
                this.uploadSuccess(result.hash);
                return;
            }

            if(result.third){
                await this.uploadThird(result.url, result.post, file).then(res =>{
                }, res => {
                    that.show_msg(res, 'danger');
                    throw Error();
                });

                await this.completeUpload().then(res =>{
                    result = res
                }, res => {
                    that.show_msg(res, 'danger');
                    throw Error();
                });

            }else{
                var chunkSize = result.chunksize;
                var chunks = result.chunks;
                if(chunks == 1){
                    await this.uploadPart(file, 1).then(res =>{
                        result = res
                    }, res => {
                        that.show_msg(res, 'danger');
                        throw Error();
                    });
                }else{
                    var blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice;
                    for (chunk = 1; chunk <= chunks; chunk++) {
                        var start = (chunk - 1) * chunkSize;
                        var end = start + chunkSize > file.size ? file.size : start + chunkSize;
                        var blob = blobSlice.call(file, start, end);
                        await this.uploadPart(blob, chunk).then(res =>{
                            that.loaded_size = end
                            result = res
                        }, res => {
                            that.show_msg(res, 'danger');
                            throw Error();
                        });
                    }
                }
            }
            this.uploadSuccess(result.hash);
        },
        async preUpload(){ //文件预上传，极速秒传查询
            var postData = {
                csrf_token: this.input.csrf_token,
                name: this.input.name,
                hash: this.input.hash,
                size: this.input.size,
                show: this.input.show?'1':'0',
                ispwd: this.input.ispwd?'1':'0',
                pwd: this.input.pwd,
            };
            var that = this;
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php?act=pre_upload',
                    data: postData,
                    dataType: 'json',
                    success: function(data) {
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error:function(){
                        layer.msg('服务器错误');
                        reject('上传失败，请稍后再试或联系站长');
                    }
                });
            })
        },
        async completeUpload(){ //第三方上传文件完成上传
            var that = this;
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'ajax.php?act=complete_upload',
                    data: {hash: that.input.hash, csrf_token: that.input.csrf_token},
                    dataType: 'json',
                    success: function(data) {
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error:function(){
                        layer.msg('服务器错误');
                        reject('上传失败，请稍后再试或联系站长');
                    }
                });
            })
        },
        async uploadPart(file, chunk){ //上传文件分片
            var that = this;
            var tempTime = new Date().getTime();
            var oloaded = 0;
            return new Promise((resolve, reject) => {
                var data = new FormData();
                data.append('file', file);
                data.append('hash', that.input.hash);
                data.append('chunk', chunk);
                data.append('csrf_token', that.input.csrf_token);
                $.ajax({
                    type : "POST",
                    url : "ajax.php?act=upload_part",
                    data : data,
                    processData: false,
                    contentType: false,
                    dataType : 'json',
                    success : function(data) {
                        if(data.code == 0 || data.code == 1){
                            resolve(data);
                        }else{
                            reject(data.msg);
                        }
                    },
                    error : function(){
                        reject('上传失败，请稍后再试或联系站长');
                    },
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (e) {
                            var progressRate = Math.round((e.loaded + that.loaded_size) / that.input.size * 100);
                            if(progressRate>100)progressRate=100;
                            that.progress = progressRate;
                            if(progressRate == 100) that.progress_tip = '正在保存中，请稍候'

                            //上传速度计算
                            var nowTime = new Date().getTime();
                            var pertime = (nowTime - tempTime) / 1000;
                            tempTime = nowTime;
                            var perload = e.loaded - oloaded;
                            oloaded = e.loaded;
	                        var speed = that.size_format(perload/pertime)+'/s';
                            that.uploadspeed = speed
                        })
                        return xhr;
                    }
                });
            })
        },
        async uploadThird(url, postdata, file){ //第三方上传文件
            var that = this;
            var tempTime = new Date().getTime();
            var oloaded = 0;
            return new Promise((resolve, reject) => {
                var data = new FormData();
                for(key in postdata){
                    data.append(key, postdata[key]);
                }
                data.append('file', file);
                $.ajax({
                    type : "POST",
                    url : url,
                    data : data,
                    processData: false,
                    contentType: false,
                    dataType : 'html',
                    success : function(data) {
                        resolve();
                    },
                    error : function(){
                        reject('上传失败，请稍后再试或联系站长');
                    },
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function (e) {
                            var progressRate = Math.round((e.loaded + that.loaded_size) / that.input.size * 100);
                            if(progressRate>100)progressRate=100;
                            that.progress = progressRate;
                            if(progressRate == 100) that.progress_tip = '正在保存中，请稍候'

                            //上传速度计算
                            var nowTime = new Date().getTime();
                            var pertime = (nowTime - tempTime) / 1000;
                            tempTime = nowTime;
                            var perload = e.loaded - oloaded;
                            oloaded = e.loaded;
	                        var speed = that.size_format(perload/pertime)+'/s';
                            that.uploadspeed = speed
                        })
                        return xhr;
                    }
                });
            })
        },
        async getFileHash(file){ //获取文件MD5
            var that = this;
            this.filename = '正在读取文件(0%)'

            return new Promise((resolve) => {
                var fileReader = new FileReader(),
                    blobSlice = File.prototype.mozSlice || File.prototype.webkitSlice || File.prototype.slice,
                    chunkSize = 2097152,
                    chunks = Math.ceil(file.size / chunkSize),
                    currentChunk = 0,
                    spark = new SparkMD5();

                loadNext();

                fileReader.onload = function(e) {
                    spark.appendBinary(e.target.result);
                    currentChunk++;
                    var progressRate = Math.round(currentChunk / chunks * 100);
                    that.filename = '正在读取文件('+progressRate+'%)'
                    if (currentChunk < chunks) {
                        loadNext();
                    }
                    else {
                        resolve(spark.end());
                    }
                };

                function loadNext() {
                    var start = currentChunk * chunkSize,
                        end = start + chunkSize >= file.size ? file.size : start + chunkSize;
                    fileReader.readAsBinaryString(blobSlice.call(file, start, end));
                };
            })
        },
        uploadSuccess(hash){
            var lastTime = (new Date().getTime() - this.beginTime) / 1000;
            var jumpurl = "file.php?hash="+hash;
            if(this.input.ispwd && this.input.pwd!=''){
                jumpurl+='&pwd='+this.input.pwd;
            }
            this.show_msg('上传成功！总用时：'+lastTime.toFixed(2)+'秒。正在跳转到文件查看页面...');
            setTimeout(function(){ window.location.href=jumpurl; }, 800);
        },
        size_format(size){
            var units = 'B';
            if(size/1024>1){
                size = size/1024;
                units = 'KB';
            }
            if(size/1024>1){
                size = size/1024;
                units = 'MB';
            }
            if(size/1024>1){
                size = size/1024;
                units = 'GB';
            }
            return size.toFixed(2)+units;
        }
    }
})