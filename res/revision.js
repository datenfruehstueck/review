$(function(){
    $('#modal_decisionletter').on('shown.bs.modal', function(event) {
        //show a decision letter
        var decision = $(event.relatedTarget).data('decision');
        $(this).find('#modal_decisionletter_title').text(decision['title']);
        $(this).find('.modal-body').html(decision['letter']);
    });

    $('#modal_revise').on('shown.bs.modal', function(event) {
        //page and submit handling for revisions
        var goToStep = function(step) {
            $('#modal_revise_step1, #modal_revise_step2, #modal_revise_step3, #modal_revise_step4').addClass('d-none');
            $('#modal_revise_step' + step).removeClass('d-none');
            $('#modal_revise_back, #modal_revise_next').data('step', step);
            switch (step) {
                case 1:
                    $('#modal_revise_back').addClass('d-none');
                    $('#modal_revise_next').html('Next step &rarr;');
                    $('#modal_revise .form-group.author').each(function(i, elem) {
                        if(i > 0) {
                            if($(elem).find('input[name="author_firstname[]"]').val() == '' &&
                                $(elem).find('input[name="author_lastname[]"]').val() == '' &&
                                $(elem).find('input[name="author_email[]"]').val() == '') {

                                $(elem).remove();
                            }
                        }
                    });
                    break;
                case 2:
                case 3:
                    $('#modal_revise_back').removeClass('d-none');
                    $('#modal_revise_next').html('Next step &rarr;');
                    break;
                case 4:
                    $('#modal_revise_back').removeClass('d-none');
                    $('#modal_revise_next').html('Submit now!');
                    $('#modal_revise_step4 [data-summary]').each(function(i, elem) {
                        var values = [];
                        $($(elem).data('summary')).each(function(i, elem) {
                            if(typeof(elem.type) == 'undefined' || elem.type != 'checkbox' || elem.checked) {
                                if(typeof(elem.files) != 'undefined' && elem.files && elem.files.length > 0) {
                                    values.push(elem.files[0].name + ' (' + (elem.files[0].size/(1024*1024)).toFixed(2) + ' MByte)');
                                } else if(elem.tagName.toLowerCase() == 'textarea') {
                                    values.push($(elem).val().replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2'));
                                } else {
                                    values.push($(elem).val());
                                }
                            }
                        });
                        $(elem).html(values.join(', '));
                    });
                    break;
            }
        };

        //author handling
        $('#modal_revise_addauthor').off('click').on('click', function(elem) {
            $('#modal_revise .form-group.author').first().clone().insertBefore('#modal_revise .form-group.addauthor');
            $('#modal_revise .form-group.author').last().find('input').val('');
        });

        //initiate submission data
        var revision_data = $(event.relatedTarget).data('revision'),
            i;
        if(typeof(revision_data['revised_submission']) != 'undefined') {
            $('#modal_revise input[name="revised_submission"]').val(revision_data['revised_submission']);
            $('#modal_revise input[name="title"]').val(revision_data['title']);
            $('#modal_revise textarea[name="abstract"]').val(revision_data['abstract']);
            for(i = 0; i < revision_data['keywords'].length; i++) {
                $('#modal_revise input[name="keywords[]"][value="' + revision_data['keywords'][i] + '"]').get(0).checked = true;
            }
            for(i = 0; i < revision_data['authors'].length; i++) {
                var last_author_elem = $('#modal_revise .form-group.author').last();
                $(last_author_elem).find('select[name="author_salutation[]"]').val(revision_data['authors'][i]['salutation']);
                $(last_author_elem).find('input[name="author_firstname[]"]').val(revision_data['authors'][i]['firstname']);
                $(last_author_elem).find('input[name="author_lastname[]"]').val(revision_data['authors'][i]['lastname']);
                $(last_author_elem).find('input[name="author_email[]"]').val(revision_data['authors'][i]['email']);
                $('#modal_revise_addauthor').click();
            }
        }

        //handle buttons and show first step
        goToStep(1);
        $('#modal_revise_back').off('click').on('click', function(elem) {
            var currentStep = parseInt($('#modal_revise_back').data('step'));
            if(currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });
        $('#modal_revise_next').off('click').on('click', function(elem) {
            var currentStep = parseInt($('#modal_revise_next').data('step'));
            if(currentStep < 4) {
                goToStep(currentStep + 1);
            } else {
                $('#modal_revise form').submit();
            }
        });
    });
});
