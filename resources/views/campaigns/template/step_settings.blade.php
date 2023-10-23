<div class="modal right fade" id="stepSettingModal" tabindex="-1" role="dialog" aria-labelledby="stepSettingModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 flex-column align-items-start px-3">
                <h5 class="modal-title" id="stepSettingModalLabel">Step Settings</h5>
                <span id="step_subject_span"></span>
                <button type="button" class="btn-pl-close" data-bs-dismiss="modal" aria-label="Close"><span class="material-symbols-rounded fs-3 me-2 fw-bold">cancel</span></button>
            </div>
            <form class="modal-body" id="settings_modal">
                @csrf
                <input type="hidden" id="step_id_modal" value="" name="step_id">
                <div class="setting_card d-flex flex-row mb-4 align-items-center shadow-sm py-2 px-2">
                    <span class="material-symbols-rounded fs-5 me-2 fw-bold">schedule</span>
                    <span class="me-3">Days to wait before next step:</span>
                    <input type="number" class="form-control number_input" id="settings_next_step_wait_time" name="next_step_wait_time" value="1">
                </div>
                <div id="dataAdd">

                </div>
                <div class="d-flex align-item-center justify-content-start gap-3">
                    <button class="add_condition btn btn-default" id="addCondition" type="button" onclick="addRow(this.form);">
                        <span class="material-symbols-rounded fs-5 me-1 fw-bold">add</span> Add condition
                    </button>
                    <button class="add_condition btn btn-primary" type="button" id="apply_condition">
                        <span class="material-symbols-rounded fs-6 me-1 fw-bold">task_alt</span> Apply
                    </button>
                </div>
                <p style="color:green;display:none" id="setting_saved">Settings Saved successfully</p>
            </form>

        </div><!-- modal-content -->
    </div><!-- modal-dialog -->
</div>