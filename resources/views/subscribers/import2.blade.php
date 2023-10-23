@extends('layouts.core.frontend')

@section('title', $list->name . ": " . trans('messages.import'))

@section('page_header')

    @include("lists._header")

@endsection

@section('content')

	@include("lists._menu")

    <div class="row">
        <div class="col-md-6">
            <h2 class="my-4">
                {{ trans('messages.subscribers.import_csv') }}
            </h2>
            <p class="">{!! trans('messages.subscribers.import_csv.intro', [
                'csv_link' => url('files/csv_import_example.csv')
            ]) !!}</p>

            <a href="{{ action('SubscriberController@import2Wizard', $list->uid) }}" class="btn btn-mc_primary start-import">{{ trans('messages.subscriber.import.start') }}</a>
        </div>
    </div>
	
	<script>
        var wizard;

        $(function() {
            $('.start-import').click(function(e) {
                e.preventDefault();
                var url = $(this).attr('href');

                wizard = new Popup({
                    url: url
                });
                wizard.load();
            });
        });

        class Mapping {
            constructor() {
                var _this = this;
                
                _this.data = [];

                // check/uncheck column
                $('.select-field').on('change', function() {
                    var checked = $(this).is(':checked');

                    if (checked) {
                        _this.selectColumn($(this).closest('li'));
                    } else {
                        _this.collapseColumn();
                    }
                });
                
                // choose field import option
                $('.field-option-radio').change(function() {
                    _this.setColumn($(this).closest('li'));                    
                    _this.selectOption($(this).closest('.field-option'));
                });

                // choose field import option
                $('.done-button').click(function() {
                    _this.setColumn($(this).closest('li'));

                    _this.done();                   
                });
                
                // choose field import option
                $('.change-option').click(function() {
                    _this.setColumn($(this).closest('li'));

                    // show option done desc
                    _this.option.find('.option-done').hide();                           
                    _this.option.find('.option-empty').show();
                    _this.option.find('.option-update').show();
                });
                
                // choose field import option
                $('.field-save-button').click(function() {
                    _this.setColumn($(this).closest('li'));
                    _this.save();
                });

                $('.toogle-icon').click(function() {
                    _this.setColumn($(this).closest('li'));

                    if (!_this.valid()) {
                        // notify
                        notify('error', '{{ trans('messages.notify.error') }}', '{{ trans('messages.subscriber.import.something_wrong') }}');
                        return;
                    }

                    _this.collapseColumn();
                });

                $('.quick-info select').change(function() {
                    _this.setColumn($(this).closest('li'));

                    var value = $(this).val();                  

                    if (value == 'more') {
                        _this.openColumn();
                    }
                });

                // change quick option
                $('.quick-info.exist select').change(function() {
                    _this.setColumn($(this).closest('li'));

                    var value = $(this).val();

                    if(value != 'more') {
                        _this.option.find('.data-associated-to').val(value).change();
                    }
                });

                // start
                $('.start-button').click(function(e){
                    e.preventDefault();

                    if (_this.validAll()) {
                        _this.collapseColumnAll();

                        alert(JSON.stringify(_this.getData()));

                        var url = $(this).attr('href');
                        wizard.load(url);
                    } else {
                    }
                });
            }

            setColumn(selector) {
                var _this = this;

                _this.column = selector;
                _this.options = _this.column.find('.field-options');

                // find selected option
                if(_this.column.find('.field-option-radio:checked').length) {
                    _this.setOption(_this.column.find('.field-option-radio:checked').closest('.field-option'));
                }
            }

            selectColumn(selector) {
                var _this = this;

                _this.column = selector;
                _this.options = _this.column.find('.field-options');

                _this.openColumn();
            }

            unselectColumn() {
                var _this = this;

                // click toggle
                _this.column.find('.select-field').trigger('click');
            } 

            openColumn() {
                var _this = this;

                _this.column.addClass('more-option');
                // show toggle icon
                _this.column.find('.toogle-icon').show();

                // hide quick info
                _this.column.find('.quick-info').hide();
            }

            collapseColumn() {
                var _this = this;

                _this.column.removeClass('more-option');
                // show toggle icon
                _this.column.find('.toogle-icon').hide();

                // update quick info
                _this.column.find('.quick-info').hide();
                if (_this.optionType == 'exist') {
                    _this.column.find('.quick-info.exist').show();
                    _this.column.find('.quick-info.exist select').val(_this.option.find('.data-associated-to').val()).change();
                } else if (_this.optionType == 'create') {
                    _this.column.find('.quick-info.create').show();

                    _this.column.removeClass('more-option');
                    _this.column.find('.quick-info.create option[data-value="create-field"]')
                        .attr('value', _this.option.find('[name=new_field]').val());

                    _this.column.find('.quick-info.create option[data-value="create-field"]')
                        .html(_this.option.find('[name=new_field]').val());

                    _this.column.find('.quick-info.create select').select2("destroy");
                    _this.column.find('.quick-info.create select').select2();
                    _this.column.find('.quick-info.create select').val(_this.option.find('[name=new_field]').val()).change();
                    
                }
            }

            setOption(selector) {
                var _this = this;

                _this.option = selector;

                // get type
                if (_this.option.find('.data-associated-to').length) {
                    _this.optionType = 'exist';
                } else if (_this.option.find('.data-create').length) {
                    _this.optionType = 'create';
                } else {
                    _this.optionType = 'skip';
                }
            }

            selectOption(selector) {
                var _this = this;

                _this.setOption(selector);

                // collapse all
                _this.options.find('.field-option').removeClass('current');
                _this.options.find('.option-update').hide();
                _this.options.find('.option-done').hide();                           
                _this.options.find('.option-empty').show();

                // show current
                _this.option.addClass('current');
                _this.option.find('.option-update').show();
            }

            valid() {
                var _this = this;

                // no option selected
                if (typeof(_this.option) == 'undefined') {
                    // notify
                    notify('error', '{{ trans('messages.notify.error') }}', '{{ trans('messages.subscriber.import.must_select_field_option') }}');
                    return;
                }

                // get type
                if (_this.optionType == 'exist') {
                    if (_this.option.find('.data-associated-to').val() == '') {
                        _this.option.find('.data-associated-to').parent().addClass('has-error');
                        return false;
                    } else {
                        _this.option.find('.data-associated-to').parent().removeClass('has-error');
                        return true;
                    }                    
                } else if (_this.optionType == 'create') {
                    // check field selectbox
                    if (_this.option.find('.data-create').val() == '') {
                        _this.option.find('.data-create').parent().addClass('has-error');
                        return false;
                    } else {
                        _this.option.find('.data-create').parent().removeClass('has-error');
                        return true;
                    }
                }

                return true;
            }

            done() {
                var _this = this;
                
                if (_this.valid()) {
                    // show option done desc
                    _this.option.find('.option-done').show();                          
                    _this.option.find('.option-empty').hide();
                    _this.option.find('.option-update').hide();

                    if (_this.optionType == 'exist') {
                        _this.option.find('.option-done .field_name').html(_this.option.find('.data-associated-to').val());
                    } else if (_this.optionType == 'create') {
                        _this.option.find('.option-done .field_name').html(_this.option.find('.data-create').val());                            
                        _this.option.find('.option-done .field_type').html(_this.option.find('.data-type').val()); 
                    }
                }
            }

            validAll() {
                var _this = this;

                // empty checked
                if (!$('.select-field:checked').length) {
                    // notify
                    notify('error', '{{ trans('messages.notify.error') }}', '{{ trans('messages.subscriber.import.mapping_empty') }}');
                    return false;
                }

                // validate all
                var hasEmail = false;
                var mappedFields = [];
                $('.select-field:checked').each(function() {
                    _this.setColumn($(this).closest('li'));
                    if (!_this.valid()) {
                        return false;
                    }

                    // check email field
                    if (_this.optionType == 'exist' && _this.option.find('.data-associated-to').val() == 'EMAIL') {
                        hasEmail = true;
                    }

                    // 
                    var fieldName;
                    if (_this.optionType == 'exist') {
                        fieldName = _this.option.find('.data-associated-to').val();
                    } else if (_this.optionType == 'create') {
                        fieldName = _this.option.find('[name=new_field]').val();
                    }

                    if (mappedFields.includes(fieldName)) {
                        // notify
                        notify('error', '{{ trans('messages.notify.error') }}', '{{ trans('messages.subscriber.import.multiple_field_error') }}');
                        return false;
                    }

                    mappedFields.push(fieldName);
                });

                // empty checked
                if (!hasEmail) {
                    // notify
                    notify('error', '{{ trans('messages.notify.error') }}', '{{ trans('messages.subscriber.import.no_email_field') }}');
                    return;
                }

                return true;
            }

            getData() {
                var _this = this;

                var data = [];
                $('.select-field:checked').each(function() {
                    var row = {}

                    _this.setColumn($(this).closest('li'));

                    if (!_this.valid()) {
                        return;
                    }

                    // check type
                    row.input = _this.column.find('[name=input]').val();
                    if (_this.optionType == 'exist') {
                        row.associated_to = _this.option.find('.data-associated-to').val();
                    } else if (_this.optionType == 'create') {
                        row.create = _this.option.find('[name=new_field]').val();
                        row.type = _this.option.find('.data-type').val();
                    }

                    data.push(row);
                });

                console.log(data);
                return data;
            }

            save() {
                var _this = this;

                if (!_this.valid()) {
                    // notify
                    notify('error', '{{ trans('messages.notify.error') }}', '{{ trans('messages.subscriber.import.something_wrong') }}');
                    return;
                }

                // done
                _this.done();

                // collapse column
                _this.collapseColumn();

                if (_this.optionType == 'skip') {
                    _this.unselectColumn();
                }
            }

            collapseColumnAll() {
                var _this = this;

                $('.select-field:checked').each(function() {
                    var row = {}

                    _this.setColumn($(this).closest('li'));

                    // collapse column
                    _this.collapseColumn();
                });
            }
        }
	</script>
@endsection
