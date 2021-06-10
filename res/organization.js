$(function(){
    $('#modal_assignreview').on('show.bs.modal', function(event) {
        //showing the "assign a review to a submission" modal
        var submission_id = $(event.relatedTarget).data('submission'),
            submission_keywords = $(event.relatedTarget).data('keywords') + '',
            submission_person_string = $(event.relatedTarget).data('persons') + '',
            submission_persons = submission_person_string.indexOf(',') > 0 ? submission_person_string.split(',') : [submission_person_string];
        $('#modal_assignreview .submission_keywords').html(submission_keywords);
        $('#modal_assignreview input[name="submission"]').val(submission_id);
        $('#modal_assignreview .reviewers tr').removeClass('table-dark');
        for(var i = 0; i < submission_persons.length; i++) {
            $('#modal_assignreview .reviewers tr[data-person="' + submission_persons[i] + '"]').addClass('table-dark');
        }
    });

    $('#modal_assignmultiplereviews').on('show.bs.modal', function(event) {
        //showing the "assign multiple reviews" modal
        var is_reviewer_allowed_for_submission = function(submission, reviewer) {
                var author_persons = ($(submission).data('persons')+'').split(','),
                    reviewer_person = $(reviewer).data('person')*1;
                for(var i = 0; i < author_persons.length; i++) {
                    if(author_persons[i]*1 === reviewer_person && reviewer_person > 0) {
                        return false;
                    }
                }
                return true;
            },
            assign_reviewer = function(submission, reviewer) {
                if($(submission).length > 0 && $(reviewer).length > 0) {
                    if (is_reviewer_allowed_for_submission(submission, reviewer)) {
                        $(submission).val($(reviewer).attr('value'));
                    }
                }
            },
            assign_random = function(submission, reviewers) {
                if(reviewers.length == 0) {
                    return false;
                } else {
                    var i = Math.floor(Math.random() * reviewers.length),
                        random_reviewer = reviewers[i];
                    if(is_reviewer_allowed_for_submission(submission, random_reviewer)) {
                        assign_reviewer(submission, random_reviewer);
                        return true;
                    } else {
                        reviewers.splice(i, 1);
                        return assign_random(submission, reviewers);
                    }
                }
            },
            get_proximity = function(submission_keywords, reviewer_keywords) {
                var matches = 0;
                for(var i = 0; i < submission_keywords.length; i++) {
                    for(var j = 0; j < reviewer_keywords.length; j++) {
                        if(submission_keywords[i] == reviewer_keywords[j]) {
                            matches++;
                            break;
                        }
                    }
                }
                var matching_share_of_submission = matches/submission_keywords.length,
                    matching_share_of_reviewer = matches/reviewer_keywords.length;
                return (matching_share_of_submission + matching_share_of_reviewer)/2;
            };

        $('#modal_assignmultiplereviews select[name*="submissions"]').each(function(i) {
            var submission = this;
            $(submission).children('option[data-person]').each(function(j) {
                if(!is_reviewer_allowed_for_submission(submission, this)) {
                    $(this).attr('disabled', 'disabled');
                }
            });
        });

        $('#modal_assignmultiplereviews .autoselect').off('click').on('click', function(event) {
            event.preventDefault();
            var mode = $(this).data('order');
            $('#modal_assignmultiplereviews select[name*="submissions"]').each(function(i) {
                if($(this).val() == '0') {
                    switch(mode) {
                        case 'random':
                            assign_random(this, $(this).children('option[data-person]:enabled'));
                            break;
                        case 'keywords':
                            var highest_proximity = -1,
                                highest_proximity_reviewer = null,
                                submission_keywords = ($(this).data('keywords')+'').split(',');
                            $(this).children('option[data-person]:enabled').each(function(j) {
                                var proximity = get_proximity(submission_keywords, ($(this).data('keywords')+'').split(','));
                                if(proximity > highest_proximity) {
                                    highest_proximity = proximity;
                                    highest_proximity_reviewer = this;
                                }
                            });
                            if(highest_proximity_reviewer) {
                                assign_reviewer(this, highest_proximity_reviewer);
                            }
                            break;
                        case 'reviews':
                            var lowest_reviews = 1000,
                                lowest_reviews_reviewer = null;
                            $(this).children('option[data-person]:enabled').each(function(j) {
                                var value = $(this).attr('value'),
                                    reviews = $(this).data('open-reviews')*1,
                                    current_sets = $('#modal_assignmultiplereviews select[name*="submissions"]').map(function(){ return $(this).val() }).get();
                                for(var k = 0; k < current_sets.length; k++) {
                                    if(current_sets[k] == value) {
                                        reviews += 1;
                                    }
                                }
                                if(reviews < lowest_reviews) {
                                    lowest_reviews = reviews;
                                    lowest_reviews_reviewer = this;
                                }
                            });
                            if(lowest_reviews_reviewer) {
                                assign_reviewer(this, lowest_reviews_reviewer);
                            }
                            break;
                        default:
                            assign_reviewer(this, $(this).children('option[data-person="' + mode + '"]:enabled'));
                    }
                }
            });
        });
    });

    $('#modal_review').on('show.bs.modal', function(event) {
        //showing the "submit a review" modal
        var review_id = $(event.relatedTarget).data('review');
        $('#modal_review input[name="review"]').val(review_id);
    });

    $('select[name="decision"]').off('change').on('change', function(event) {
        if($(this).val() != '') {
            //update the decision letter
            var update_decision_letter = true;
            if (parseInt($('textarea[name="decision_letter"]').data('dirty')) === 1) {
                update_decision_letter = false;
                if (confirm('You have edited the decision letter already. Do you want it to be overwritten with the new decision?')) {
                    $('textarea[name="decision_letter"]').data('dirty', 0);
                    update_decision_letter = true;
                }
            }
            if (update_decision_letter) {
                var num_of_reviewers = $('.review_comment').length,
                    decision_letter = $('textarea[name="decision_letter"]').data('salutation') +
                        '\n\n' +
                        'thank you once again for your submission with the title ' + '\n' +
                        $('#submission_title').text() + '\n\n' +
                        'As we have now collected feedback on your work, the decision that has been made is to ' +
                        $(this).find('option:selected').text().toLowerCase() + '. ' +
                        ($(this).val().indexOf('Revis') >= 0 ? 'To do so, please go through all the feedback provided to you and prepare both a revised version of your submission and a raw-text document in which you thoroughly address each point that has been raised (Action Letter). You can then submit both your revised version and your action letter through the review system. ' : '') +
                        '\n\n' +
                        'The feedback from ' + num_of_reviewers + ' blinded reviewer' + (num_of_reviewers > 1 ? 's' : '') + ' can be found below.' + '\n\n' +
                        'Kind regards, ' + '\n' +
                        $('textarea[name="decision_letter"]').data('greeting') + '\n\n\n';
                $('.review_comment').each(function (i, elem) {
                    decision_letter += '------' + '\n' + 'Reviewer ' + (i+1) + '\n\n' +
                        $(this).text() + '\n\n\n';
                });
                $('textarea[name="decision_letter"]').val(decision_letter);
                //unset dirty flag that has just been set in change event
                $('textarea[name="decision_letter"]').data('dirty', 0);
            }
        }
    });

    $('textarea[name="decision_letter"]').off('change').on('change', function(event) {
        //mark the decision letter dirty to prevent it from being overwritten
        $(this).data('dirty', 1);
    });
});
