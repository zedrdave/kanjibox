<?php

if (!empty($_SESSION['user'])) {
    log_error('You need to be logged to access this function.', false, true);
}

if ((isset($params['type']) && $params['type'] != 'general') && !empty($_SESSION['cur_session'])) {
    log_error('You need to be using Drill or Quiz mode to send this type of feedback.', false, true);


    if (!$form_options = $_SESSION['cur_session']->feedbackFormOptions($params['sid'])) {
        die('No feedback options for this type of drill/quiz');
    }

    $select = '';
    $forms = '';
    ?>

    <?php

    echo make_toggle_visibility("<ol class=\"feedback-instructions\"><li>This form is <em>not</em> for help requests: only for bugs and content errors.</li><li><a href=\"http://kanjibox.net/kb/page/faq/\">Read the FAQ first</a>.</li><li>Be as complete as possible in your report: select relevant entries and add comments if necessary.</li><li>For any content mistake, check first with an <a href=\"http://www.csse.monash.edu.au/~jwb/cgi-bin/wwwjdic.cgi?1C\">authoritative dictionary</a>.</li><li><a href=\"http://kanjibox.net/kb/page/faq/#quizlevels\">Read this</a> before reporting any level errors in Quiz mode.</li><li>Only report a specific problem once.</li><li><a href=\"http://kanjibox.net/kb/page/faq/#corrections\">Read the FAQ</a>.</li><li>If the issue if blocking game-play altogether, don't hesitate to <a href=\"mailto:support@kanjibox.net\">contact me</a> directly.</li><li>If you are an Elite user or reporting translation mistakes in French/Spanish/German, consider using the 'Edit' button (next to the feedback button) to make the correction directly.</li></ol>",
        0, 'Click <span style="color:blue">here</span> first if this is your first time sending feedback! &raquo;<br/>');

    foreach ($form_options as $id => $options) {
        if (count($form_options) > 1) {
            $select .= '<option value="' . $id . '">' . $options['title'] . ' </option>';
            $forms .= '<form id="feedback_options_' . $id . '" class="feedback_option_form" action="' . SERVER_URL . 'ajax/submit_feedback/" method="post" style="display:none;"><fieldset>';
        } else {
            $forms .= '<h3>' . $options['title'] . ':</h3>';
            $forms .= '<form id="feedback_options_' . $id . '" class="feedback_option_form" action="' . SERVER_URL . 'ajax/submit_feedback/" method="post" ' . $submit_cond . '><fieldset>';
        }

        $forms .= '<input type="hidden" name="type" value="' . $options['type'] . '" />';
        $forms .= '<p>';

        if (is_array($options['param_1'])) {
            $forms .= $options['param_1_title'] . ' ' . get_select_menu($options['param_1'], 'form_' . $id . '_param_1',
                    '', '', '...', 'param_1', ($options['param_1_required'] ? 'form_required' : ''));
        } else {
            $forms .= $options['param_1_title'] . '<input type="hidden" id="form_' . $id . '_param_1" name="param_1" value="' . (int) $options['param_1'] . '" ' . ($options['param_1_required'] ? 'class="form_required"' : '') . ' /> ';
        }

        if (isset($options['param_2'])) {
            $forms .= ' ' . $options['param_2_title'] . ' ';
            $forms .= ' ' . get_select_menu($options['param_2'], 'form_' . $id . '_param_2', '', '', '...', 'param_2',
                    ($options['param_2_required'] ? 'form_required' : ''));
        }

        if (isset($options['param_3'])) {
            if (is_array($options['param_3'])) {
                $forms .= $options['param_3_title'] . ' ' . get_select_menu($options['param_3'], 'param_3', '', '',
                        '...', ($options['param_3_required'] ? 'form_required' : ''));
            } else {
                $forms .= $options['param_3_title'] . '<input type="hidden" id="form_' . $id . '_param_3" name="param_3" value="' . (int) $options['param_3'] . '" ' . (@$options['param_3_required'] ? 'class="form_required"' : '') . ' /> ';
            }
        }

        $forms .= '</fieldset><fieldset><legend>Additional comments (optional):</legend><textarea id="comment" name="comment"></textarea></fieldset>';
        $forms .= '</form>';
    }

    if ($select) {
        echo "<select id=\"feedback_form_select\" onchange=\"\$('.feedback_option_form').hide();\$('#feedback_options_' + this.value).show();\"><option value=\"\">Please select...</option>$select</select>";
    }
}