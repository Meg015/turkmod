function addDlRow(name,url){
                    const row=document.createElement('div');
                    row.className='dl-row ui-admin-download-row';
                    row.innerHTML='<input type="text" name="dl_name[]" class="ui-admin-form-control ui-admin-download-name" placeholder="Kaynak adı" value="'+(name||'')+'">'
                        +'<input type="url" name="dl_url[]" class="ui-admin-form-control ui-admin-download-url" placeholder="https://..." value="'+(url||'')+'">'
                        +'<button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-ui-remove-closest=".dl-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>';
                    document.getElementById('dlRows').appendChild(row);
                }
                if (window.TMUI && typeof window.TMUI.registerAction === 'function') {
                    window.TMUI.registerAction('addDlRow', function() { addDlRow(); });
                }
                document.getElementById('topicForm').addEventListener('submit',function(){
                    const names=document.querySelectorAll('input[name="dl_name[]"]');
                    const urls=document.querySelectorAll('input[name="dl_url[]"]');
                    const lines=[];
                    names.forEach((n,i)=>{const u=urls[i]?.value?.trim();if(u) lines.push((n.value.trim()||'Link')+'|'+u);});
                    document.getElementById('dlHidden').value=lines.join('\n');
                });
