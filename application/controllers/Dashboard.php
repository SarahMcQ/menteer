<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Menteer
 *
 * Original Code is Menteer, Released January 2015
 *
 * The initial developer of the Original Code is CSCI (CareerSkillsIncubator) with
 * the generous support from CIRA.ca (Community Investment Program)
 *
 *
 */

// main dashboard for mentor and mentee
class Dashboard extends CI_Controller
{


    public function __construct()
    {
        parent::__construct();

        if (!$this->ion_auth->logged_in()) {
            redirect('/', 'refresh');
        }

        if ($this->ion_auth->is_admin() && $this->uri->segment(2) != 'force') {
            redirect('/admin', 'refresh');
        }

        $this->load->model('Application_model');
        $this->load->helper('form');
        $this->user = $this->Application_model->get(
            array('table' => 'users', 'id' => $this->session->userdata('user_id'))
        );

        // check for setup
        if ($this->user['is_setup'] == 0) {
            $this->_setup();
        }

        // check if user updating questionnaire
        if ($this->user['is_setup'] == 1 && $this->user['frm_data']!='') {
            $this->_setupUpdate();
        }

        // is this user a mentor or menteer or both
        $val = $this->Application_model->get(
            array(
                'table' => 'users_answers',
                'user_id' => $this->session->userdata('user_id'),
                'questionnaire_id' => TYPE_QUESTION_ID,
            )
        );

        // figure out which type we are
        switch ($val['questionnaire_answer_id']) {
            case MENTOR_ID:
                $this->session->set_userdata('user_kind', 'mentor');
                break;
            case MENTEE_ID:
                $this->session->set_userdata('user_kind', 'mentee');
                break;
            default:
                $this->session->set_userdata('user_kind', 'both');
        }

        if ($this->user['agree'] == 1 && $this->uri->segment(2)!= 'force') {
            // check if we need to show the user matches before we begin
            // check for matches available otherwise go to dashboard

            if ($this->input->get('skip') == 1) {
                $this->session->set_userdata('skip_matches', true);
            }else{
                $this->session->set_userdata('skip_matches', false);
            }

            if ($this->user['is_matched'] == 0 && $this->session->userdata('skip_matches') == false) {
                $this->load->library('matcher');
                $matches = $this->matcher->get_matches($this->session->userdata('user_id'));

                //echo "<pre>";
                //print_r($matches);
                //echo "</pre>";
                //exit;

                //if (is_array($matches) && count($matches) > 0) {
                    $this->session->set_userdata('matches', $matches);
                    $this->session->set_userdata('skip_matches', true);
                    //redirect('/chooser');
                //} else {
                //    $this->session->set_userdata('matches', $matches);
                //    $this->session->set_userdata('skip_matches', true);
                //}
            }
        }

        $this->data = array();
    }

    // main
    public function index()
    {

        $this->data['page'] = 'dash';
        $this->data['user'] = $this->user;
        $this->data['survey'] = $this->Application_model->get(array('table'=>'survey'));

        // check if user has completed the survey
        $check = $this->Application_model->get(array('table'=>'survey_answers','users_id'=>$this->session->userdata('user_id')));

        $this->data['no_survey'] = 0;

        if(is_array($check) && count($check) > 0) {
            $this->data['no_survey'] = 1;
        }

        $this->load->view('/dash/header', $this->data);
        $this->load->view('/dash/index', $this->data);
        $this->load->view('/dash/footer', $this->data);

    }

    // save survey
    public function save_survey()
    {
        $insert['data']['users_id'] = $this->session->userdata('user_id');
        $insert['data']['question'] = $this->input->post('question');
        $insert['data']['answer'] = $this->input->post('answer');
        $insert['table'] = 'survey_answers';
        $insert['data']['stamp'] = date("Y-m-d H:i:s");

        $this->Application_model->insert($insert);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Thanks for completing our survey!</div>'
        );

        redirect('/dashboard','refresh');

    }

    //de-cloak
    public function decloak()
    {

        if($this->session->userdata('cloaking')==1) {
           $this->session->set_userdata( array('cloaking' => '0') );

            $this->ion_auth->force_login(encrypt_url(1));

            redirect('/admin','refresh');
        }else{
            die('Not Authorized');
        }

    }

    //force view
    public function force($id=0)
    {

        $id = $this->input->get('id');

        if($this->session->userdata('user_id') == 1){

            $user_id = decrypt_url($id);

            $this->session->set_userdata( array('cloaking' => '1') );

            $this->ion_auth->force_login($id);

            //print_r($this->session->userdata());
            redirect('/dashboard','refresh');

        }else{
            die('Not Authorized');
        }

    }

    //end match
    public function end($match_id=0)
    {

        $match_id = $this->input->get('id');

        if(decrypt_url($match_id) > 0 && $this->user['menteer_type']==37) {

            $update['id'] = $this->session->userdata('user_id');
            $update['data']['is_matched'] = 0;
            $update['data']['match_status'] = 'pending';
            $update['table'] = 'users';
            $this->Application_model->update($update);

            $update['id'] = decrypt_url($match_id);
            $update['data']['is_matched'] = 0;
            $update['data']['match_status'] = 'pending';
            $update['table'] = 'users';
            $this->Application_model->update($update);

            $update = array();

            $update['data']['mentee_id'] = decrypt_url($match_id);
            $update['data']['mentor_id'] = $this->session->userdata('user_id');
            $update['data']['stamp'] = date("Y-m-d H:i:s");
            $update['table'] = 'matches_ended';
            $this->Application_model->insert($update);

            $this->session->set_flashdata(
                'message',
                '<div class="alert alert-success">Match Has Ended</div>'
            );
        }

        redirect('/dashboard','refresh');

    }

    //revoke match request
    public function revoke($match_id=0)
    {

        $match_id = $this->input->get('id');

        if(decrypt_url($match_id) > 0 && $this->user['menteer_type']==38) {

            $update['id'] = $this->session->userdata('user_id');
            $update['data']['is_matched'] = 0;
            $update['data']['match_status'] = 'pending';
            $update['table'] = 'users';
            $this->Application_model->update($update);

            $update['id'] = $mentor_id = decrypt_url($match_id);
            $update['data']['is_matched'] = 0;
            $update['data']['match_status'] = 'pending';
            $update['table'] = 'users';
            $this->Application_model->update($update);

            $update = array();

            $update['data']['mentor_id'] = decrypt_url($match_id);
            $update['data']['mentee_id'] = $this->session->userdata('user_id');
            $update['data']['stamp'] = date("Y-m-d H:i:s");
            $update['table'] = 'matches_revoked';
            $this->Application_model->insert($update);

            // inform mentor
            $mentor = $this->Application_model->get(array('table'=>'users','id'=>$mentor_id));

            $data = array();

            $message = $this->load->view('/chooser/email/match_revoke', $data, true);
            $this->email->clear();
            $this->email->from($this->config->item('admin_email', 'ion_auth'), $this->config->item('site_title', 'ion_auth'));
            $this->email->to($mentor['email']);
            $this->email->subject('Mentee has Cancelled Their Match Request');
            $this->email->message($message);

            $result = $this->email->send(); // @todo handle false send result

            $this->session->set_flashdata(
                'message',
                '<div class="alert alert-success">Match Request Revoked</div>'
            );
        }

        redirect('/dashboard','refresh');

    }

    // donation page
    public function donate()
    {

        $this->config->load('stripe');

        $config = $this->config->item('stripe');

        $this->data['content'] = $this->Application_model->get(array('table'=>'content'));

        // Load Stripe
        require($_SERVER['DOCUMENT_ROOT'].'/assets/stripe/Stripe.php');

        if ($_POST) {
            Stripe::setApiKey($config['secret-key']);

            // POSTed Variables
            $token      = $this->input->post('stripeToken');
            $first_name = $this->input->post('first-name');
            $last_name  = $this->input->post('last-name');
            $name       = $first_name . ' ' . $last_name;
            $address    = $this->input->post('address') . "\n" . $this->input->post('city') . ', ' . $this->input->post('state') . ' ' . $this->input->post('zip');
            $email      = $this->input->post('email');
            //$phone      = $this->input->post('phone');
            $amount     = (float) $this->input->post('amount');

            try {
                if ( ! isset($_POST['stripeToken']) ) {
                    throw new Exception("The Stripe Token was not generated correctly");
                }

                // Charge the card
                $donation = Stripe_Charge::create(array(
                    'card'        => $token,
                    'description' => 'Donation by ' . $name . ' (' . $email . ')',
                    'amount'      => $amount * 100,
                    'currency'    => 'cad'
                ));

                //log_message('error', $donation);

                $message_arr['name'] = $name;
                $message_arr['amount'] = $amount;
                $message_arr['address'] = $address;
                //$message_arr['phone'] = $phone;
                $message_arr['email'] = $email;
                $message_arr['date'] = date('M j, Y, g:ia', $donation['created']);
                $message_arr['transaction_id'] = $donation['id'];

                $subject = $config['email-subject'];

                $message = $this->load->view('/dash/email/receipt', $message_arr, true);
                $this->email->clear();
                $this->email->from($config['email-from']);
                $this->email->bcc($config['email-bcc']);
                $this->email->to($email);
                $this->email->subject($subject);
                $this->email->message($message);

                $result = $this->email->send();

                // Forward to "Thank You" page
                redirect($config['thank-you'],'refresh');

            } catch (Exception $e) {
                $error = $e->getMessage();
                //log_message('error', $error);

                $this->data['error'] = $error;

            }
        }


        $this->data['page'] = 'donate';
        $this->data['user'] = $this->user;
        $this->data['config'] = $config;

        $this->load->view('/dash/header', $this->data);
        $this->load->view('/dash/donate', $this->data);
        $this->load->view('/dash/footer', $this->data);
    }

    // donation page thanks page
    public function thankyou()
    {

        $this->data['content'] = $this->Application_model->get(array('table'=>'content'));

        $this->data['page'] = 'donate';
        $this->data['user'] = $this->user;

        $this->load->view('/dash/header', $this->data);
        $this->load->view('/dash/thanks', $this->data);
        $this->load->view('/dash/footer', $this->data);
    }

    // see this profile
    public function myprofile()
    {

        $this->data['page'] = 'profile';
        $this->data['me'] = $this->user;
        $this->data['user'] = $this->user;

        $this->load->view('/dash/header', $this->data);
        $this->load->view('/dash/myprofile', $this->data);
        $this->load->view('/dash/footer', $this->data);
    }

    // my events
    public function myevents()
    {

        $this->data['page'] = 'events';
        $this->data['user'] = $this->user;

        $this->data['myevents'] = $this->Application_model->get(array('table'=>'events','user_event_id'=>$this->session->userdata('user_id')));

        $this->load->view('/dash/header', $this->data);
        $this->load->view('/dash/myevents', $this->data);
        $this->load->view('/dash/footer', $this->data);
    }

    // save event
    public function save_event()
    {

        $update = array();
        $update['data']['match_id'] = $this->user['is_matched'];
        $update['data']['user_id'] = $this->session->userdata('user_id');
        $update['data']['stamp'] = date("Y-m-d H:i:s");
        $update['data']['event'] = $this->input->post('newevent');
        $update['table'] = 'events';
        $this->Application_model->insert($update);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Event Added</div>'
        );

        redirect('/dashboard/myevents','refresh');

    }

    //delete event
    public function delete_event($event_id=0)
    {
        $event_id=$this->input->get('id');
        $event_id = decrypt_url($event_id);

        $update = array();
        $update['table'] = 'events';
        $update['key'] = 'id';
        $update['value'] = $event_id;
        $this->Application_model->delete($update);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Event Removed</div>'
        );

        redirect('/dashboard/myevents','refresh');
    }

    // my tasks
    public function mytasks()
    {

        $this->data['page'] = 'tasks';
        $this->data['user'] = $this->user;

        $this->data['mytasks'] = $this->Application_model->get(array('table'=>'tasks','user_task_id'=>$this->session->userdata('user_id')));

        $this->load->view('/dash/header', $this->data);
        $this->load->view('/dash/mytasks', $this->data);
        $this->load->view('/dash/footer', $this->data);
    }

    // save task
    public function save_task()
    {

        $update = array();
        $update['data']['match_id'] = $this->user['is_matched'];
        $update['data']['user_id'] = $this->session->userdata('user_id');
        $update['data']['stamp'] = date("Y-m-d H:i:s");
        $update['data']['task'] = $this->input->post('newtask');
        $update['table'] = 'tasks';
        $this->Application_model->insert($update);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Task Added</div>'
        );

        redirect('/dashboard/mytasks','refresh');

    }

    //delete task
    public function delete_task($task_id=0)
    {
        $task_id=$this->input->get('id');

        $task_id = decrypt_url($task_id);

        $update = array();
        $update['table'] = 'tasks';
        $update['key'] = 'id';
        $update['value'] = $task_id;
        $this->Application_model->delete($update);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Task Removed</div>'
        );

        redirect('/dashboard/mytasks','refresh');
    }

    // edit questionnaire
    public function myintake()
    {

        $this->load->model('Questionnaire_model');
        $this->load->model('Matcher_model');

        $this->data['questions'] = $this->Questionnaire_model->get();
        $this->data['num_questions'] = count($this->data['questions']);

        $this->data['answers'] = $this->_extract_data($this->Matcher_model->get(array('table'=>'users_answers','user_id'=>$this->session->userdata('user_id'))));

        $this->load->view('/dash/header',$this->data);
        $this->load->view('/dash/intake',$this->data);
        $this->load->view('/dash/footer',$this->data);

    }

    // get answers
    protected function _extract_data($m_answers) {

        $question = array();

        foreach($m_answers as $item){

            if($item['questionnaire_answer_id'] == 0)
                $answer = $item['questionnaire_answer_text'];
            else
                $answer = $this->Application_model->get(array('table'=>'questionnaire_answers','id'=>$item['questionnaire_answer_id']));

            $question[$item['questionnaire_id']][] = $answer;

        }

        return $question;
    }

    // save profile info
    public function save_profile()
    {

        $this->load->library('upload');

        // save profile data
        $update['id'] = $this->session->userdata('user_id');
        $update['data']['location'] = $this->input->post('location');
        $update['data']['phone'] = $this->input->post('phone');
        $update['data']['career_status'] = $this->input->post('career_status');
        $update['data']['career_goals'] = $this->input->post('career_goals');
        $update['data']['education'] = $this->input->post('education');
        $update['data']['experience'] = $this->input->post('experience');
        $update['data']['skills'] = $this->input->post('skills');
        $update['data']['passion'] = $this->input->post('passion');
        $update['table'] = 'users';
        $this->Application_model->update($update);

        // lets save and upload their picture if available
        $config['upload_path']          = './uploads/';
        $config['allowed_types']        = 'gif|jpg|png|jpeg';
        $config['max_size']             = 50000;
        $config['max_width']            = 8000;
        $config['max_height']           = 8000;
        $config['file_ext_tolower']     = TRUE;
        //$config['min_width']            = 25;
        $config['encrypt_name']         = TRUE;

        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        $upload_errors = '';

        if(isset($_FILES['userfile']['name']) && $_FILES['userfile']['name'] != '') {

            if (!$this->upload->do_upload()) {

                $upload_errors = $this->upload->display_errors();

            } else {
                //$upload_data = array('upload_data' => $this->upload->data());


                // lets resize and crop
                if($this->upload->data('image_width') > 200) {

                    $config['image_library'] = 'gd2';
                    $config['source_image'] = './uploads/'.$this->upload->data('file_name');
                    $config['width']         = 200;

                    if($this->upload->data('file_size') <= 2000) {
                        $config['quality'] = '95%';
                    }

                    if($this->upload->data('file_size') > 2000) {
                        $config['quality'] = '85%';
                    }

                    $this->load->library('image_lib', $config);

                    $this->image_lib->resize();


                }

                $upload['id'] = $this->session->userdata('user_id');
                $upload['data']['picture'] = $this->upload->data('file_name');
                $upload['table'] = 'users';
                $this->Application_model->update($upload);

            }
        }


        if($upload_errors) {
            $this->session->set_flashdata(
                'message',
                '<div class="alert alert-danger">'.$upload_errors.'</div>'
            );
            redirect('/dashboard/myprofile','refresh');
        }else {
            $this->session->set_flashdata(
                'message',
                '<div class="alert alert-success">Profile Saved.</div>'
            );
            redirect('/dashboard','refresh');
        }


    }

    // see match profile
    public function match()
    {

        $this->data['page'] = 'profile';
        $match_id = $this->user['is_matched'];

        $this->data['match'] = $this->Application_model->get(array('table'=>'users','id'=>$match_id));

        $this->data['user'] = $this->user;

        if($this->user['is_matched'] > 0) {

            $this->load->view('/dash/header', $this->data);
            $this->load->view('/dash/match', $this->data);
            $this->load->view('/dash/footer', $this->data);

        }else{
            redirect('/dashboard','refresh');
        }

    }

    // send meeting invite with ics to your match
    public function send_meeting()
    {
        // save meeting
        if($this->user['is_matched'] > 0) {
            $update['data']['from'] = $this->session->userdata('user_id');
            $update['data']['to'] = $this->user['is_matched'];
            $update['data']['meeting_subject'] = $this->input->post('meeting_subject');
            $update['data']['meeting_desc'] = $this->input->post('meeting_desc');
            $update['data']['month'] = $this->input->post('month');
            $update['data']['day'] = $this->input->post('day');
            $update['data']['year'] = $this->input->post('year');
            $update['data']['start_ampm'] = $this->input->post('start_ampm');
            $update['data']['end_ampm'] = $this->input->post('end_ampm');
            $update['data']['stamp'] = date("Y-m-d H:i:s");
            $update['data']['start_time'] = $this->input->post('start_time');
            $update['data']['end_time'] = $this->input->post('end_time');

            $update['table'] = 'meetings';
            $update['data']['ical'] = encrypt_url($this->Application_model->insert($update));

            // send emails to each party

            $match = $this->Application_model->get(array('table'=>'users','id'=>$this->user['is_matched']));
            $update['data']['who'][] = $match['first_name'] . " " . $match['last_name'];
            $update['data']['who'][] = $this->user['first_name'] . " " . $this->user['last_name'];

            //convert date and time to nice format
            $nice_date = date('D M d, Y',strtotime($this->input->post('day')."-".$this->input->post('month')."-".$this->input->post('year')));
            $update['data']['nice_date'] = $nice_date;

            // to requesting user
            $data = array();
            $data['user'] = $this->user['first_name'] . " " . $this->user['last_name'];
            $data['message'] = $update['data'];
            $message = $this->load->view('/dash/email/meeting', $data, true);
            $this->email->clear();
            $this->email->from($this->config->item('admin_email', 'ion_auth'), $this->config->item('site_title', 'ion_auth'));
            $this->email->to($this->user['email']);

            $full_subject = "Invitation: " . $nice_date . " " . $this->input->post('start_time') . "" . $this->input->post('start_ampm') . " - " . $this->input->post('end_time') . "" . $this->input->post('end_ampm') . " (" . $this->user['first_name'] . " " . $this->user['last_name'] . ")";

            $this->email->subject($full_subject);
            $this->email->message($message);

            $result = $this->email->send(); // @todo handle false send result


            // to invitee
            $data = array();
            $data['user'] = $match['first_name'] . " " . $match['last_name'];
            $data['message'] = $update['data'];
            $message = $this->load->view('/dash/email/meeting', $data, true);
            $this->email->clear();
            $this->email->from($this->config->item('admin_email', 'ion_auth'), $this->config->item('site_title', 'ion_auth'));
            $this->email->to($match['email']);

            $full_subject = "Invitation: " . $nice_date . " " . $this->input->post('start_time') . "" . $this->input->post('start_ampm') . " - " . $this->input->post('end_time') . "" . $this->input->post('end_ampm') . " (" . $match['first_name'] . " " . $match['last_name'] . ")";

            $this->email->subject($full_subject);
            $this->email->message($message);

            $result = $this->email->send(); // @todo handle false send result


            $this->session->set_flashdata(
                'message',
                '<div class="alert alert-success">Meeting Request Sent.</div>'
            );

            redirect('/dashboard/match','refresh');
        }else{

            redirect('/dashboard','refresh');

        }

    }

    // send email message to match
    public function send_message()
    {

        // get email of match

        $match_id = $this->user['is_matched'];

        if($match_id > 0) {

            $match = $this->Application_model->get(array('table'=>'users','id'=>$match_id));

            $send_to = $match['email'];
        }

        $data = array();
        $data['user'] = $this->user['first_name'] . " " . $this->user['last_name'];
        $data['message'] = nl2br($this->input->post('message_body'));
        $message = $this->load->view('/dash/email/message', $data, true);
        $this->email->clear();
        $this->email->from($this->config->item('admin_email', 'ion_auth'), $this->config->item('site_title', 'ion_auth'));
        $this->email->to($send_to);
        $this->email->subject($this->input->post('message_subject'));
        $this->email->message($message);

        $result = $this->email->send(); // @todo handle false send result

        // increment number of messages sent
        $update['id'] = $this->session->userdata('user_id');
        $update['data']['num_messages_sent'] = $this->user['num_messages_sent'] + 1;
        $update['table'] = 'users';
        $this->Application_model->update($update);

        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Message Sent.</div>'
        );

        redirect('/dashboard/match');

    }

    // view my settings page
    public function settings()
    {

        $this->data['page'] = 'settings';
        $this->data['user'] = $this->user;

        // get privacy settings

        $my_privacy = explode(',',$this->user['privacy_settings']);

        $this->data['settings'] = $my_privacy;

        $this->load->view('/dash/header',$this->data);
        $this->load->view('/dash/settings',$this->data);
        $this->load->view('/dash/footer',$this->data);

    }

    // delete - actually disable account for now
    public function delete()
    {

        $update['id'] = $this->session->userdata('user_id');
        $update['data']['enabled'] = 0;
        $update['data']['active'] = 0;
        $update['table'] = 'users';
        $this->Application_model->update($update);

        redirect('/logout','refresh');

    }

    // save settings
    public function settings_save()
    {

        // check if privacy settings changed

        $s1 = 0;
        $s2 = 0;
        $s3 = 0;

        if ($this->input->post('share_email'))
            $s1 = 1;

        if ($this->input->post('share_phone'))
            $s2 = 1;

        if ($this->input->post('share_location'))
            $s3 = 1;

        $update['id'] = $this->session->userdata('user_id');
        $update['data']['privacy_settings'] = $s1.",".$s2.",".$s3;
        $update['data']['enabled'] = intval($this->input->post('enabled'));

        switch($this->input->post('menteer_type')){
            case "37" :
                $update['data']['menteer_type'] = 37;
                break;
            default :
                $update['data']['menteer_type'] = 38;
        }

        $update['table'] = 'users';
        $this->Application_model->update($update);


        //check if password being changed

        if($this->input->post('oldpassword') != '') {

            // validate old password

            if ($this->ion_auth->login_check($this->session->userdata('email'), $this->input->post('oldpassword'))){

                // correct password so lets change it

                if(strlen($this->input->post('newpassword')) >= $this->config->item('min_password_length','ion_auth') && $this->input->post('newpassword') <= $this->config->item('max_password_length','ion_auth')) {

                    $this->ion_auth->change_password(
                        $this->session->userdata('email'),
                        $this->input->post('oldpassword'),
                        $this->input->post('newpassword')
                    );

                    $this->session->set_flashdata(
                        'message',
                        '<div class="alert alert-success">Settings Updated.</div>'
                    );

                    redirect('/dashboard', 'refresh');
                }else{

                    $this->session->set_flashdata('message', '<div class="alert alert-danger">Password must be between 8 and 20 characters in length.</div>');
                    redirect('/dashboard/settings','refresh');
                }

            }else{

                $this->session->set_flashdata('message', '<div class="alert alert-danger">Incorrect Password.</div>');
                redirect('/dashboard/settings','refresh');

            }

        }
        $this->session->set_flashdata(
            'message',
            '<div class="alert alert-success">Settings Updated.</div>'
        );
        redirect('/dashboard','refresh');


    }

    // initial registration agreement of terms disclaimer user must accept or logout of system.
    // will be prompted each time they login until they accept the site agreement
    public function accept()
    {

        $update['id'] = $this->session->userdata('user_id');
        $update['data']['agree'] = '1';
        $update['table'] = 'users';
        $this->Application_model->update($update);

        redirect('/dashboard','refresh');

    }

    /**
     * User Setup - Run Once Only!
     */
    protected function _setup() {

        // we must have data
        if($this->user['frm_data'] == '') {
            return false;
        }

        //update user table first to ensure we don't run this again
        $update_user = array(
            'id' => $this->session->userdata('user_id'),
            'data' => array('is_setup' => 1),
            'table' => 'users'
        );

        $this->Application_model->update($update_user);

        $frm_data_arr = json_decode($this->user['frm_data']);

        $batch = array();

        foreach($frm_data_arr as $data) {

            // only use questionnaire fields
            if(intval($data->name) > 0) {

                // get questionnaire info
                $question_arr = $this->Application_model->get(array('table'=>'questionnaire','id'=>$data->name));

                switch($question_arr['type']){
                    case 'list':
                    case 'yesno':

                        // break up list and prep
                        $list_arr = explode(',',$data->value);

                        foreach($list_arr as $item){

                            $list = array(
                                'user_id' => $this->session->userdata('user_id'),
                                'questionnaire_id' => $data->name,
                                'questionnaire_answer_text' => strtolower(trim($item)),
                                'questionnaire_answer_id' => 0
                            );
                            $batch[] = $list;

                        }

                        break;

                    case 'open':

                        $list = array(
                            'user_id' => $this->session->userdata('user_id'),
                            'questionnaire_id' => $data->name,
                            'questionnaire_answer_text' => strtolower(trim($data->value)),
                            'questionnaire_answer_id' => 0
                        );
                        $batch[] = $list;

                        break;
                    default:

                        $list = array(
                            'user_id' => $this->session->userdata('user_id'),
                            'questionnaire_id' => $data->name,
                            'questionnaire_answer_text' => '',
                            'questionnaire_answer_id' => $data->value
                        );
                        $batch[] = $list;
                }
            }
        }

        $save_data = array(
            'data' => $batch,
            'table' => 'users_answers'
        );

        $this->Application_model->save_batch($save_data);

        // is this user a mentor or menteer or both
        $val = $this->Application_model->get(array('table'=>'users_answers','user_id'=>$this->session->userdata('user_id'),'questionnaire_id'=>TYPE_QUESTION_ID,));

        // mentor is default if both types selected
        if ($val['questionnaire_answer_id']== 41)
            $val['questionnaire_answer_id'] = 37;

        //clean the user table of this information for security
        $update_user = array(
            'id' => $this->session->userdata('user_id'),
            'data' => array('frm_data' => '','menteer_type' => $val['questionnaire_answer_id']),
            'table' => 'users'
        );
        $this->Application_model->update($update_user);

        // send the mentor an email
        if($val['questionnaire_answer_id'] == 37) {
            $data = array();

            $message = $this->load->view('/dash/email/welcome_mentor', $data, true);
            $this->email->clear();
            $this->email->from($this->config->item('admin_email', 'ion_auth'), $this->config->item('site_title', 'ion_auth'));
            $this->email->to($this->user['email']);
            $this->email->subject('Welcome to GlobalHerizons.ca');
            $this->email->message($message);

            $result = $this->email->send(); // @todo handle false send result
        }
    }

    // update questionnaire
    protected function _setupUpdate() {

        if($this->session->userdata('user_id') > 0 ) {
            // we must have data
            if ($this->user['frm_data'] == '') {
                return false;
            }

            $frm_data_arr = json_decode($this->user['frm_data']);

            $batch = array();

            foreach ($frm_data_arr as $data) {

                // only use questionnaire fields
                if (intval($data->name) > 0) {

                    // get questionnaire info
                    $question_arr = $this->Application_model->get(
                        array('table' => 'questionnaire', 'id' => $data->name)
                    );

                    switch ($question_arr['type']) {
                        case 'list':
                        case 'yesno':

                            // break up list and prep
                            $list_arr = explode(',', $data->value);

                            foreach ($list_arr as $item) {

                                $list = array(
                                    'user_id' => $this->session->userdata('user_id'),
                                    'questionnaire_id' => $data->name,
                                    'questionnaire_answer_text' => strtolower(trim($item)),
                                    'questionnaire_answer_id' => 0
                                );
                                $batch[] = $list;

                            }

                            break;

                        case 'open':

                            $list = array(
                                'user_id' => $this->session->userdata('user_id'),
                                'questionnaire_id' => $data->name,
                                'questionnaire_answer_text' => strtolower(trim($data->value)),
                                'questionnaire_answer_id' => 0
                            );
                            $batch[] = $list;

                            break;
                        default:

                            $list = array(
                                'user_id' => $this->session->userdata('user_id'),
                                'questionnaire_id' => $data->name,
                                'questionnaire_answer_text' => '',
                                'questionnaire_answer_id' => $data->value
                            );
                            $batch[] = $list;
                    }
                }
            }

            // delete existing data for user
            $this->Application_model->delete(
                array('table' => 'users_answers', 'key' => 'user_id', 'value' => $this->session->userdata('user_id'))
            );

            $save_data = array(
                'data' => $batch,
                'table' => 'users_answers'
            );

            $this->Application_model->save_batch($save_data);

            //clean the user table of this information for security
            $update_user = array(
                'id' => $this->session->userdata('user_id'),
                'data' => array('frm_data' => ''),
                'table' => 'users'
            );
            $this->Application_model->update($update_user);

        }
    }
}