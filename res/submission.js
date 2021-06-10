$(function(){
    $('#modal_submission').on('shown.bs.modal', function(event) {
        //page and submit handling
        var goToStep = function(step) {
			if(step == 2 && $('#modal_submission .form-group.author input[name="author_email[]"]').first().val().trim() == '') {
				alert('At least one author is mandatory.');
				return false;
			}
			
            $('#modal_submission_step1, #modal_submission_step2, #modal_submission_step3').addClass('d-none');
            $('#modal_submission_step' + step).removeClass('d-none');
            $('#modal_submission_back, #modal_submission_next').data('step', step);
            switch (step) {
                case 1:
                    $('#modal_submission_back').addClass('d-none');
                    $('#modal_submission_next').html('Next step &rarr;');
                    $('#modal_submission .form-group.author').each(function(i, elem) {
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
                    $('#modal_submission_back').removeClass('d-none');
                    $('#modal_submission_next').html('Next step &rarr;');
                    break;
                case 3:
                    $('#modal_submission_back').removeClass('d-none');
                    $('#modal_submission_next').html('Submit now!');
                    $('#modal_submission_step3 [data-summary]').each(function(i, elem) {
                        var values = [];
                        $($(elem).data('summary')).each(function(i, elem) {
                            if(typeof(elem.type) == 'undefined' || elem.type != 'checkbox' || elem.checked) {
                                if(typeof(elem.files) != 'undefined' && elem.files && elem.files.length > 0) {
                                    values.push(elem.files[0].name + ' (' + (elem.files[0].size/(1024*1024)).toFixed(2) + ' MByte)');
                                } else {
                                    values.push($(elem).val());
                                }
                            }
                        });
                        $(elem).text(values.join(', '));
                    });
                    break;
            }
        };
        goToStep(1);
        $('#modal_submission_back').off('click').on('click', function(elem) {
            var currentStep = parseInt($('#modal_submission_back').data('step'));
            if(currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });
        $('#modal_submission_next').off('click').on('click', function(elem) {
            var currentStep = parseInt($('#modal_submission_next').data('step'));
            if(currentStep < 3) {
                goToStep(currentStep + 1);
            } else {
                $('#modal_submission form').submit();
            }
        });

        //author handling
        $('#modal_submission_addauthor').off('click').on('click', function(elem) {
            $('#modal_submission .form-group.author').first().clone().insertBefore('#modal_submission .form-group.addauthor');
            $('#modal_submission .form-group.author').last().find('input').val('');
        });
    });
});
