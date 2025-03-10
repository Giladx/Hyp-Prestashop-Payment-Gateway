<?php
class AdminGiladxHypController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true; // Enable Bootstrap for styling
    }

    public function initContent()
    {
        parent::initContent();
        $this->setTemplate('module:giladx_hyp/views/templates/admin/configure.tpl'); // Path to your template
    }
}