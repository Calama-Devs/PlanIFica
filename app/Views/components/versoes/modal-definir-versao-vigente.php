<div class="modal fade" id="modal-definir-versao-vigente" tabindex="-1" aria-labelledby="ModalLabel" style="display: none;" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ModalLabel">Confirmação necessária</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            
            <form id="definirVersaoVigente" class="forms-sample" method="post" action='<?php echo base_url('sys/versao/definirVersaoVigente'); ?>'>
                <?php echo csrf_field() ?>
                <input type="hidden" id="definir-id" name="id" />
                <div class="modal-body text-break">Confirma a definição da versão <strong id='definir-nome'></strong> como vigente?</div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger me-2">Definir</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>