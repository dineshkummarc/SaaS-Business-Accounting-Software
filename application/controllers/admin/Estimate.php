<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Estimate extends Home_Controller {

    public function __construct()
    {
        parent::__construct();
        //check auth
        if (!is_user()) {
            redirect(base_url());
        }
    }


    public function index()
    {
        $data = array();
        //initialize pagination
        $this->load->library('pagination');
        $config['base_url'] = base_url('admin/estimate/index');
        $total_row = $this->admin_model->get_estimates_by_type(1 , 0, 0, 1, $estimate=2);
        $config['total_rows'] = $total_row;
        $config['per_page'] = 12;
        $this->pagination->initialize($config);
        
        $page = $this->security->xss_clean($this->input->get('page'));
        if (empty($page)) {
            $page = 0;
        }
        if ($page != 0) {
            $page = $page - 1;
        }

        $data['page_title'] = 'Estimate';   
        $data['page'] = 'Estimate';
        $data['main_page'] = 'Sales';
        $data['estimates'] = $this->admin_model->get_estimates_by_type(0 , $config['per_page'], $page * $config['per_page'], 1, $estimate=2);
        $data['customers'] = $this->admin_model->get_by_user('customers');
        $data['main_content'] = $this->load->view('admin/user/estimates',$data,TRUE);
        $this->load->view('admin/index',$data);
    }

    public function create()
    {
        $data = array();
        $data['page_title'] = 'Create Estimate';      
        $data['page'] = 'Estimate'; 
        $data['invoice'] = FALSE;
        $data['products'] = $this->admin_model->get_by_user_and_type('products', 'is_sell');
        
        $data['total_tax'] = $this->admin_model->get_invoice_total_taxes(0);
        $data['asign_taxs'] = $this->admin_model->get_invoice_taxes(0);
        $data['gsts'] = $this->admin_model->get_user_taxes_by_gst();
        $data['countries'] = $this->admin_model->select_asc('country');
        $data['customers'] = $this->admin_model->get_by_user('customers');
        $data['total'] = $this->admin_model->get_total_by_user('invoice', 2);
        $data['main_content'] = $this->load->view('admin/user/estimate_create',$data,TRUE);
        $this->load->view('admin/index',$data);
    }

    public function edit($id)
    {
        $data = array();
        $data['page_title'] = 'Edit Estimate';         
        $data['page_sub'] = 'Edit';         
        $data['page'] = 'Estimate'; 
        $data['invoice'] = $this->admin_model->get_by_md5_data($id, 'invoice');
        $data['products'] = $this->admin_model->get_by_user_and_type('products', 'is_sell');

        $data['total_tax'] = $this->admin_model->get_invoice_total_taxes($data['invoice'][0]['id']);
        $data['asign_taxs'] = $this->admin_model->get_invoice_taxes($data['invoice'][0]['id']);
        $data['gsts'] = $this->admin_model->get_user_taxes_by_gst();

        $data['customers'] = $this->admin_model->get_by_user('customers');
        $data['total'] = $this->admin_model->get_total_by_user('invoice', 2);
        $data['main_content'] = $this->load->view('admin/user/estimate_create',$data,TRUE);
        $this->load->view('admin/index',$data);
    }

    public function details($id)
    {
        $data = array();
        $data['invoice'] = $this->admin_model->get_invoice_details($id);
        $data['page_title'] = 'Estimate details';      
        $data['page'] = 'Estimate'; 
        $data['main_content'] = $this->load->view('admin/user/estimate_save',$data,TRUE);
        $this->load->view('admin/index',$data);
    }

    public function add_product($product_id)
    {   
        $product = $this->admin_model->get_by_id($product_id, 'products');
        $data = array();
        $data['taxes'] = $this->admin_model->get_by_user('tax');
        $data['product'] = $product; 
        if (!empty($product)) {
            $loaded = $this->load->view('admin/user/include/product_list',$data, TRUE);
            echo json_encode(array('st' => 1, 'loaded' => $loaded));
        }
    }


    public function save()
    {
        $data = array();
        $data['page_title'] = 'Invoice';      
        $data['page'] = 'Invoice'; 
        $data['products'] = $this->admin_model->get_by_user('invoice');
        $data['main_content'] = $this->load->view('admin/user/estimate_save',$data,TRUE);
        $this->load->view('admin/index',$data);
    }
    

    public function add()
    {

        $data = array();
        $id = $this->input->post('id', true);
        
        //validate inputs
        $this->form_validation->set_rules('customer', trans('add-customer-error-msg'), 'required');

        if ($this->form_validation->run() === false) {
            $data['status'] = 2;
            $data['error'] = validation_errors();
            echo json_encode($data);
        } else {

            if (!empty($this->input->post('discount'))) {
                $discount = $this->input->post('discount');
            }else{
                $discount = 0;
            }

            if (!empty($id)) {
                $created_at = get_by_id($id, 'invoice')->created_at;
            }else{
                $created_at = my_date_now();
            }
            
            $estimate = array(
                'user_id' => user()->id,
                'business_id' => $this->business->uid,
                'title' => $this->input->post('title', true),
                'type' => 2,
                'status' => 0,
                'summary' => $this->input->post('summary', true),
                'customer' => $this->input->post('customer', true),
                'number' => $this->input->post('number', true),
                'poso_number' => $this->input->post('poso_number', true),
                'date' => $this->input->post('date', true),
                'discount' => $discount,
                'expire_on' => $this->input->post('expire_on', true),
                'footer_note' => $this->input->post('footer_note', true),
                'sub_total' => $this->input->post('sub_total', true),
                'grand_total' => $this->input->post('grand_total', true),
                'convert_total' => '0.00',
                'created_at' => $created_at
            );

            $estimate = $this->security->xss_clean($estimate);
            
            if (!empty($id)) {
                $this->admin_model->delete_items($id, 'invoice_taxes');
                $this->admin_model->delete_items($id, 'invoice_items');
                $this->admin_model->edit_option($estimate, $id, 'invoice');
            } else {

                if (check_package_limit('estimate') != -2) {
                    $total = get_total_value('invoice', 2);
                    if ($total >= check_package_limit('estimate')):
                        $msg = trans('reached-limit').', '.trans('package-limit-msg');
                        $data['status'] = 4;
                        $data['error'] = $msg;
                        echo json_encode($data);
                        exit();
                    endif;
                }

                $id = $this->admin_model->insert($estimate, 'invoice');
            }
            

            $product_id = $this->input->post('product_ids', true);
            $items = $this->input->post('items', true);
            $details = $this->input->post('details', true);
            $price = $this->input->post('price', true);
            $quantity = $this->input->post('quantity', true);
            $total_price = $this->input->post('total_price', true);

            if (!empty($items)) {
                for ($i=0; $i < count($items); $i++) { 

                    if ($product_id[$i] == 0) {
                        $product_data=array(
                            'user_id' => user()->id,
                            'business_id' => $this->business->uid,
                            'name' => $items[$i],
                            'price' => $price[$i],
                            'quantity' => 0,
                            'is_sell' =>1,
                            'income_category' => 0,
                            'is_buy' => 0,
                            'expense_category' => 0
                        );
                        $item_product_id = $this->admin_model->insert($product_data, 'products');
                    }else{
                        $item_product_id = $product_id[$i];
                    }

                    $item_data = array(
                        'invoice_id' => $id,
                        'item' => $item_product_id,
                        'details' => htmlspecialchars($details[$i], ENT_QUOTES),
                        'price' => $price[$i],
                        'qty' => $quantity[$i],
                        'total' => $total_price[$i]
                    );
                    $this->admin_model->insert($item_data, 'invoice_items');

                    if (!empty($details[$i])) {
                        //$this->update_product($item_product_id, $details[$i]);
                    }
                }
            }

            $taxes = $this->input->post('taxes', true);
            if (!empty($taxes)) {
                foreach ($taxes as $tax) {
                    if ($tax != 0) {
                        $tax_data = array(
                            'invoice_id' => $id,
                            'tax_id' => $tax,
                        );
                        $this->admin_model->insert($tax_data, 'invoice_taxes');
                    }
                }
            }

            $data['status'] = 1;
            $data['invoice_id'] = md5($id);
            echo json_encode($data);

        }
        
    }


    // update product
    public function update_product($product_id, $details)
    {
        $data = array(
            'details' => $details
        );
        $this->admin_model->edit_option($data, $product_id, 'products');
    }



    public function convert($id)
    {   
        $invoice = $this->admin_model->get_by_md5_id($id, 'invoice');
        $data=array(
            'type' => 1,
            'status' => 0
        );
        $data = $this->security->xss_clean($data);
        $this->admin_model->edit_option($data, $invoice->id, 'invoice');
        echo json_encode(array('st' => 1));
    }


    public function export_pdf($id)
    {
        $data = array();
        $data['invoice'] = $this->admin_model->get_by_md5_id($id, 'invoice');
        $data['page_title'] = 'Estimate Export';      
        $data['page'] = 'Estimate';
        //load library
        $this->load->library('pdf');
        //load view page
        $this->pdf->load_view('admin/user/estimate_view', $data);
        $this->pdf->render();
        $this->pdf->stream("estimate.pdf");
   }


    public function send($id)
    {   
        if($_POST)
        {   
            $customer_id = $this->input->post('customer_id', true);
            $is_myself = $this->input->post('is_myself', true);
            $estimate = $this->admin_model->get_invoice_details($id);
            $customer = $this->admin_model->get_customer_info($customer_id);
            $data = array();
            if (isset($is_myself)) {
                $data['email_myself'] = $this->input->post('email_myself', true);
            } else {
                $data['email_myself'] = '';
            }

            $data['email_to'] = $this->input->post('email_to', true);
            $data['message'] = $this->input->post('message', true);
            $data['subject'] = 'Estimate #' . $estimate->id . ' from '.user()->name;
            $data['invoice'] = $estimate;
            $data['logo'] = base_url($estimate->logo);
            $data['currency_code'] = $customer->currency_code;
            $data['currency_symbol'] = $customer->currency_symbol;
            $data['type'] = 'Estimate';
            $data['html_content'] = $this->load->view('email_template/invoice', $data, true);
            $send_data = $this->email_model->send($data['email_to'], $data['subject'], $data['html_content'], $data['email_myself']);

            if ($send_data == true) {
                $sent_data = array(
                    'is_sent' => 1,
                    'sent_date' => my_date_now()
                );
                $this->admin_model->edit_option($sent_data, $id, 'invoice');

                $data = array();
                $data['status'] = 1;
                echo json_encode($data);
            } else {
                $data = array();
                $data['status'] = 2;
                echo json_encode($data);
            }

        }      
        
    }

   
    public function ajax_add_product()
    {   
        if($_POST)
        {   
            $data=array();
            $data=array(
                'user_id' => user()->id,
                'business_id' => $this->business->uid,
                'name' => $this->input->post('name', true),
                'price' => $this->input->post('price', true),
                'is_sell' => 0,
                'income_category' => 0,
                'is_buy' => 0,
                'expense_category' => 0,
                'details' => $this->input->post('details')
            );
            $data = $this->security->xss_clean($data);
            $id = $this->admin_model->insert($data, 'products');
            $data['products'] = $this->admin_model->get_by_user('products');
            $load_product = $this->load->view('admin/user/include/invoice_product_list', $data,TRUE);
            echo json_encode(array('st' => 1, 'load_product' => $load_product));
        }      
        
    }


    //add invoice payment record
    public function record_payment()
    {   
        if($_POST)
        {   
            $id = $this->input->post('invoice_id', true);
            $invoice = $this->admin_model->get_by_md5_id($id, 'invoice');

            $data=array(
                'payment_date' => $this->input->post('payment_date', true),
                'amount' => $this->input->post('amount', true),
                'customer_id' => $invoice->customer,
                'invoice_id' => $invoice->id,
                'payment_method' => $this->input->post('payment_method', true),
                'note' => $this->input->post('note', true)
            );
            $data = $this->security->xss_clean($data);
            $id = $this->admin_model->insert($data, 'payment_records');

            $invoice_data=array(
                'status' => 2
            );
            $this->admin_model->edit_option($invoice_data, $invoice->id, 'invoice');
            echo json_encode(array('st' => 1));
        }      
        
    }

    public function approve_invoice($id) 
    {
        $invoice = $this->admin_model->get_by_md5_id($id, 'invoice');
        //echo "<pre>"; print_r($invoice); exit();
        $data = array(
            'status' => 1
        );
        $data = $this->security->xss_clean($data);
        $this->admin_model->edit_option($data, $invoice->id, 'invoice');
        echo json_encode(array('st' => 1));
    }


    public function deactive($id) 
    {
        $data = array(
            'status' => 0
        );
        $data = $this->security->xss_clean($data);
        $this->admin_model->update($data, $id,'invoice');
        $this->session->set_flashdata('msg', trans('msg-deactivated')); 
        redirect(base_url('admin/services'));
    }

    public function delete($id)
    {
        $this->admin_model->delete($id,'invoice'); 
        echo json_encode(array('st' => 1));
    }

}
	

