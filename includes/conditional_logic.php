<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Elementor\Widget_Base;
use ElementorPro\Modules\Forms\Classes;
use Elementor\Controls_Manager;
use ElementorPro\Plugin;
class Superaddons_Elementor_Conditional_Logic {
    private $check_validate = false;
    private $data_repeater_fields = array();
    public function __construct() {
        add_action( 'elementor-pro/forms/pre_render', [ $this, 'pre_render' ], 10, 3 );
        add_action( 'elementor/element/form/section_form_fields/before_section_end', [ $this, 'add_pattern_field_control' ], 100, 2 );
        add_action("wp_enqueue_scripts",array($this,"add_libs"));
        add_action( "elementor/controls/register", array( $this, 'register_controls' ) );
        add_action("elementor_pro/forms/validation/text",array($this,"validation"),9,3);
        add_action("elementor/editor/before_enqueue_scripts",array($this,"add_lib_backend"));
        add_filter("elementor_pro/forms/record/actions_before",array($this,"custom_actions"),10,2);
    }
    function add_libs() {
        wp_register_script( 'elementor_pro_conditional_logic', ELEMENTOR_CONDITIONAL_LOGIC_PLUGIN_URL.'libs/conditional_logic.js', ["jquery" ]);
        wp_enqueue_style( 'elementor_pro_conditional_logic', ELEMENTOR_CONDITIONAL_LOGIC_PLUGIN_URL.'libs/conditional_logic.css');
        wp_enqueue_script('elementor_pro_conditional_logic');
    }
    function add_lib_backend() {
        wp_register_script( 'elementor_pro_conditional_logic_editor', ELEMENTOR_CONDITIONAL_LOGIC_PLUGIN_URL.'libs/conditional_logic_editor.js', ["jquery" ]);
        wp_enqueue_style( 'elementor_pro_conditional_logic_editor', ELEMENTOR_CONDITIONAL_LOGIC_PLUGIN_URL.'libs/conditional_logic_editor.css');
        wp_enqueue_script('elementor_pro_conditional_logic_editor');
        $check = get_option( '_redmuber_item_1473');
        wp_localize_script(
            'elementor_pro_conditional_logic_editor',
            'elementor_pro_conditional_logic_editor',
            array(
                'pro' => $check,
            )
);
    }
    function custom_actions($record, $form){
        return $record;
    }
    function elementor_conditional_logic_check_single($value_id,$operator,$value){
        $rs = false;
        switch($operator) {
              case "==":
                    if( $value_id == $value){
                        $rs = true;
                    }   
                break;
              case "!=":
                    if( $value_id != $value){
                            $rs = true;
                    }
                    break;
              case "e":
                    if( $value_id == ""){
                            $rs = true;
                    }
                    break;
              case "!e":
                    if( $value_id != ""){
                            $rs = true;
                    }
                    break;
              case "c":
                    if( str_contains($value_id,$value) ){
                        $rs = true;
                    }
                    break;
               case "!c":
                    if( !str_contains($value_id,$value) ){
                        $rs = true;
                    }
                break;
               case "^":
                    if( str_starts_with($value_id,$value) ){
                        $rs = true;
                    }
                    break;
               case "~":
                    if( str_ends_with($value_id,$value) ){
                        $rs = true;
                    }
                    break;
               case ">":
                    if( $value_id > $value){
                        $rs = true;
                    }
                    break;
                case "<":
                    if( $value_id < $value){
                            $rs = true;
                    }
                    break;
                case "array":
                    $values= array_map('trim', explode(',', $value));
                    if( in_array($value_id,$values)){
                            $rs = true;
                    }
                    break;
                case "!array":
                    $values= array_map('trim', explode(',', $value));
                    if( !in_array($value_id,$values)){
                            $rs = true;
                    }
                    break;
                case "array_contain":
                    $values= array_map('trim', explode(',', $value));
                    foreach($values as $vl){
                        if( str_contains($value_id,$vl) ){
                            $rs = true;
                        }
                    }
                    break;
                case "!array_contain":
                    $values= array_map('trim', explode(',', $value));
                    $rs = true;
                    foreach($values as $vl){
                        if( str_contains($value_id,$vl) ){
                            $rs = false;
                           
                        }
                    }    
                    break;   
              default: 
                break; 
            }
            return $rs;
    }
    function validation($field, $record, $ajax_handler){
        if($this->check_validate == false){
            $form_settings = $record->get("form_settings");
            $form_fields = $record->get("fields");
            $data_repeater_fields = array();
            $temp = array();
            $start = false;
            foreach ( $form_settings["form_fields"] as $id => $field ) { 
                if($field["field_type"] == "repeater_start"){  
                    $start = true;
                }
                if( $field["field_type"] == "repeater"){
                    $data_repeater_fields[$field["custom_id"]] = $temp;
                    $temp = array();
                    $start = false;
                }
                if($start){
                   $temp[] = $field["custom_id"]; 
                }
            }      
            $this->data_repeater_fields = $data_repeater_fields;
            foreach ( $form_settings["form_fields"] as $id => $field ) {
                if($field["conditional_logic"] == "yes"){
                    $display = $field["conditional_logic_display"];
                    $trigger = $field["conditional_logic_trigger"];
                    $rs = array();
                    foreach ( $field["conditional_logic_datas"] as $logic_key => $logic_values ) {
                        if(isset($form_fields[$logic_values["conditional_logic_id"]])){
                            $value_id = $form_fields[$logic_values["conditional_logic_id"]]["value"];
                            if( is_array($value_id) ){
                                $value_id = implode(", ",$value_id);
                            }
                        }else{
                           $value_id = $logic_values["conditional_logic_id"];
                        }
                        $operator = $logic_values["conditional_logic_operator"];
                        $value = $logic_values["conditional_logic_value"];
                        $rs[] = $this->elementor_conditional_logic_check_single($value_id,$operator,$value);
                    }
                    if( $trigger =="ALL"  ){
                        $check_rs = true;
                        foreach ( $rs as $fkey => $fvalue ) {
                            if( $fvalue == false ){
                                $check_rs =false;
                                break;
                            }
                        }
                        if( $display == "show" ) {
                            if( $check_rs == true ){
                            }else{
                                
                                $this->remove_field_in_repeater($field,$record);
                            }
                        }else{
                            if( $check_rs == true ){
                                $this->remove_field_in_repeater($field,$record);
                            }else{
                            }
                        }
                  }else{
                        $check_rs = false;
                        foreach ( $rs as $fkey => $fvalue ) {
                            if( $fvalue == true ){
                                $check_rs =true;
                                break;
                            }
                        }
                        if( $display == "show" ) {
                            if( $check_rs == true ){
                            }else{
                                $this->remove_field_in_repeater($field,$record);
                            }
                        }else{
                            if( $check_rs == true ){
                                $this->remove_field_in_repeater($field,$record);
                            }else{
                            }
                        }   
                  }
                }
             }
         }else {
         }
         $this->check_validate = true;  
         foreach ( $form_settings["form_fields"] as $id => $field ) {
            $array_remove = array("rednumber_dev_check","1023-01-01","1234567892","rednumber_dev_check@test.com");
            if( in_array($field["value"],$array_remove)){
                $record->remove_field($id); 
            }
         }
    }
    function remove_field_in_repeater($field,$record){
        if($field["field_type"] == "repeater"){
            $datas = $this->data_repeater_fields;
            if( isset($datas[$field["custom_id"]]) ){
                foreach($datas[$field["custom_id"]] as $f ){
                    $record->remove_field($f);
                }
            }
        }
        $record->remove_field($field["custom_id"]);
    }
    public function register_controls( $controls_manager ) {
        include ELEMENTOR_CONDITIONAL_LOGIC_PLUGIN_PATH.'controls/repeater.php' ;
        include ELEMENTOR_CONDITIONAL_LOGIC_PLUGIN_PATH.'controls/select.php' ;
        $controls_manager->register( new \Conditional_Repeater_Control() );
        $controls_manager->register( new \Superaddons_Control_Select() );
    }
    function add_pattern_field_control($widget,$args ){
        $elementor = \Elementor\Plugin::instance();
        $control_data = $elementor->controls_manager->get_control_from_stack( $widget->get_unique_name(), 'form_fields' );
        if ( is_wp_error( $control_data ) ) {
            return;
        }
        $check = get_option( '_redmuber_item_1473');
        if($check == "ok"){
            $options_logic = array(
                            "==" => esc_html__("is","conditional-logic-for-elementor-forms"),
                            "!=" => esc_html__("not is","conditional-logic-for-elementor-forms"),
                            "e" => esc_html__("empty","conditional-logic-for-elementor-forms"),
                            "!e" => esc_html__("not empty","conditional-logic-for-elementor-forms"),
                            "c" => esc_html__("contains","conditional-logic-for-elementor-forms"),
                            "!c" => esc_html__("does not contain","conditional-logic-for-elementor-forms"),
                            "^" => esc_html__("starts with","conditional-logic-for-elementor-forms"),
                            "~" => esc_html__("ends with","conditional-logic-for-elementor-forms"),
                            ">" => esc_html__("greater than","conditional-logic-for-elementor-forms"),
                            "<" => esc_html__("less than","conditional-logic-for-elementor-forms"),
                            "array" => esc_html__("list array (a,b,c)","conditional-logic-for-elementor-forms"),
                            "!array" => esc_html__("not list array (a,b,c)","conditional-logic-for-elementor-forms"),
                            "array_contain" => esc_html__("list array contain (a,b,c)","conditional-logic-for-elementor-forms"),
                            "!array_contain" => esc_html__("not list array contain (a,b,c)","conditional-logic-for-elementor-forms"),
                        );
            $options_pro = array();
        }else{
            $options_logic = array(
                            "==" => esc_html__("is","conditional-logic-for-elementor-forms"),
                            "!=" => esc_html__("not is","conditional-logic-for-elementor-forms"),
							"==" => esc_html__("is","conditional-logic-for-elementor-forms"),
                            "!=" => esc_html__("not is","conditional-logic-for-elementor-forms"),
                            "e" => esc_html__("empty","conditional-logic-for-elementor-forms"),
                            "!e" => esc_html__("not empty","conditional-logic-for-elementor-forms"),
                            "c" => esc_html__("contains","conditional-logic-for-elementor-forms"),
                            "!c" => esc_html__("does not contain","conditional-logic-for-elementor-forms"),
                            "^" => esc_html__("starts with","conditional-logic-for-elementor-forms"),
                            "~" => esc_html__("ends with","conditional-logic-for-elementor-forms"),
                            ">" => esc_html__("greater than","conditional-logic-for-elementor-forms"),
                            "<" => esc_html__("less than","conditional-logic-for-elementor-forms"),
                        );
            $options_pro = array(
                            "9" => esc_html__("List array (a,b,c) - Pro","conditional-logic-for-elementor-forms"),
                            "10" => esc_html__("Not List array (a,b,c) - Pro","conditional-logic-for-elementor-forms"),
                            "11" => esc_html__("List array contain (a,b,c) - Pro","conditional-logic-for-elementor-forms"),
                            "12" => esc_html__("Not List array contain (a,b,c) - Pro","conditional-logic-for-elementor-forms"),
                        );  
        }

        $field_controls = [
                'conditional_logic' => [
                    'name' => 'conditional_logic',
                    'label' => esc_html__( 'Enable Conditional Logic', "conditional-logic-for-elementor-forms" ),
                    'type' => Controls_Manager::SWITCHER,
                    'tab' => 'content',
                    'condition' => [
                        'field_type!' => 'step',
                    ],
                    'inner_tab' => 'form_fields_advanced_tab',
                    'tabs_wrapper' => 'form_fields_tabs',
                ],
                'conditional_logic_display' => [
                    'name' => 'conditional_logic_display',
                    'label' => esc_html__( 'Display mode', "conditional-logic-for-elementor-forms" ),
                    'type' => Controls_Manager::CHOOSE,
                    'options' => [
                        'show' => [
                            'title' => esc_html__( 'Show if', "conditional-logic-for-elementor-forms" ),
                            'icon' => 'fa fa-eye',
                        ],
                        'hide' => [
                            'title' => esc_html__( 'Hide if', "conditional-logic-for-elementor-forms" ),
                            'icon' => 'fa fa-eye-slash',
                        ],
                    ],
                    'default' => 'show',
                    'tab' => 'content',
                    'condition' => [
                        'conditional_logic' => 'yes'
                    ],
                    'inner_tab' => 'form_fields_advanced_tab',
                    'tabs_wrapper' => 'form_fields_tabs',
                ],
                'conditional_logic_trigger' => [
                    'name' => 'conditional_logic_trigger',
                    'label' => esc_html__( 'When to Trigger', "conditional-logic-for-elementor-forms" ),
                    'type' => Controls_Manager::SELECT,
                    'options' => [
                        "ALL"=>"ALL",
                        "ANY"=>"ANY"
                    ],
                    'default' => 'ALL',
                    'tab' => 'content',
                    'condition' => [
                        'conditional_logic' => 'yes'
                    ],
                    'inner_tab' => 'form_fields_advanced_tab',
                    'tabs_wrapper' => 'form_fields_tabs',
                ],
                'conditional_logic_datas' => array(
                'name'           => 'conditional_logic_datas',
                'label'          => esc_html__( 'Fields if', "conditional-logic-for-elementor-forms" ),
                'type'           => 'conditional_logic_repeater',
                'tab'            => 'content',
                'inner_tab'      => 'form_fields_advanced_tab',
                'tabs_wrapper'   => 'form_fields_tabs',
                'fields'         => [
                    [
                        'name' => 'conditional_logic_id',
                        'label' => esc_html__( 'Field ID', "conditional-logic-for-elementor-forms" ),
                        'type' => Controls_Manager::TEXT,
                        'label_block' => true,
                        'default' => '',
                    ],
                     [
                        'name' => 'conditional_logic_operator',
                        'label' => esc_html__( 'Operator', "conditional-logic-for-elementor-forms" ),
                        'type' => 'select1',
                        'label_block' => true,
                        'options' => $options_logic,
                        'options_pro' => $options_pro,
                       'default' => '==',
                    ],
                    [
                        'name' => 'conditional_logic_value',
                        'label' => esc_html__( 'Value to compare', "conditional-logic-for-elementor-forms" ),
                        'type' => Controls_Manager::TEXT,
                        'label_block' => true,
                        'default' => '',
                    ],
                ],
                'condition' => [
                        'conditional_logic' => 'yes'
                    ],
                'style_transfer' => false,
                'title_field'    => '{{{ conditional_logic_id  }}} {{{ conditional_logic_operator  }}} {{{ conditional_logic_value  }}}',
                'default'        => array(
                    array(
                        'conditional_logic_id' => '',
                        'conditional_logic_operator' => '==',
                        'conditional_logic_value' => '',
                    ),
                   ),
                ),
                'conditional_logic_hr' => [
                    'name' => 'conditional_logic_hr',
                    'type' => Controls_Manager::DIVIDER,
                    'tab' => 'content',
                    'condition' => [
                        'conditional_logic' => 'yes'
                    ],
                    'inner_tab' => 'form_fields_advanced_tab',
                    'tabs_wrapper' => 'form_fields_tabs',
                ],

            ];
            $control_data['fields'] = $this->inject_field_controls( $control_data['fields'], $field_controls );
            $widget->update_control( 'form_fields', $control_data );
    }
    public function pre_render( $instance) {
        $datas = array();
        foreach ( $instance['form_fields'] as $item_index => $field ) :
            if ( ! empty( $field['conditional_logic'] ) && $field['conditional_logic'] == "yes" ) {
                $datas[$field["custom_id"]] = array("display"=>$field['conditional_logic_display'],"trigger"=>$field['conditional_logic_trigger'],"datas"=>$field['conditional_logic_datas'] );
            }
        endforeach;
        ?>
        <input class="conditional_logic_data_js hidden" data-form-id="<?php echo esc_attr( $instance["form_name"] ) ?>" value="<?php echo htmlspecialchars(json_encode($datas)) ?>" />
        <?php
    }
    function inject_field_controls( $array, $controls_to_inject ) {
        $keys = array_keys( $array );
        $key_index = array_search( 'required', $keys ) + 1;
        return array_merge( array_slice( $array, 0, $key_index, true ),
            $controls_to_inject,
            array_slice( $array, $key_index, null, true )
        );
    }
}
new Superaddons_Elementor_Conditional_Logic;
// php 8
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}