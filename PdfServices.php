<?php 

namespace Application\Service;

use Zend\View\Model\ViewModel;

/**
 * Generate PDF
 */
class PdfService
{

    private $options = [
        /**
         * content the max page for file PDF (0 is infinite)
         */
        'MAX_PAGE_FOR_FILE' => 5,

        /**
         * cant row for first page (0 is infinite)
         */
        'MAX_ROW_FIRST_PAGE' => 0,

        /**
         * cant row for first page (0 is infinite)
         */
        'MAX_ROW_PAGE' => 0,

        /**
         * add paginator on footer watermark
         */
        'ADD_PAGINATOR' => true,

        /**
         * fields for body table
         */
        'FIELDS' => [],
    ];

    /**
     * the url for the content body, if is null so get the body automatic
     * @var string
     */
    public $templateBody = null;

    /**
     * the url for the content pageBreak, if is null so get the page break automatic
     * @var string
     */
    public $templatePageBreak = null;

    /**
     * the url for the content add row
     * @var string
     */
    public $templateAddRow = null;

    /**
     * the url path for the watermark
     * @var string
     */
    public $templateWatermark = 'application/partials/watermark';

    /**
     * element embed body
     * @var string
     */
    public $elementEmbed = 'table';

    /**
     * element style body
     * @var string
     */
    public $elementStyle = 'width:100%;border-collapse: collapse;border-spacing: 0;';

    /**
     * base path for the pdf
     * @var string
     */
    public $path = '/var/www/inventario.itsofting/reporte';

    /**
     * function callback for add row
     * @var function
     */
    public $functionAddRow = null;

    /**
     * Get the service locator from controller
     * @var ServiceLocatorManager
     */
    protected $serviceLocator;
    
    /**
     * Get viewModel
     * @var ViewModel
     */
    protected $view;
    
    /**
     * Array data for body
     * @var array
     */
    protected $data;

    /**
     * Content view template HTML for all views
     * @var array
     */
    protected $contentTemplate = [];

    /**
     * the current Page for all PDF
     * @var integer
     */
    protected $currentPage = 1;

    /**
     * the current row
     * @var integer
     */
    protected $currentRow = 0;

    function __construct($serviceLocator)
    {
        //parent::__construct();
        $this->serviceLocator = $serviceLocator;
        $this->view = new ViewModel();
        $this->view->setTerminal(true);
        $this->name = [];
        $this->number = 0;
    }

    public function setOptions($options)
    {
        $this->options = $options + $this->options;
    }

    public function setDomPdf($domPdf)
    {
        $this->domPdf = $domPdf;
    }

    public function getDomPdf()
    {
        return $this->domPdf;
    }

    public function generate($baseName, $domPdf)
    {                   
        $html = $this->generateHtml();
        //echo $html;exit;
        $domPdf->load_html($html);
        $domPdf->render();

        if ($this->currentPage <= $this->options['MAX_PAGE_FOR_FILE'] && empty($this->data)) {
            $domPdf->stream($baseName.'.pdf');
            return 0;
        }

        $this->number++;
        $output = $domPdf->output();
        //echo $output;exit;
        $this->name[] = [
            $this->number,
            $baseName.'-'.date('YmdHis').'-'.$this->number.'.pdf'
        ];
        file_put_contents (  $this->path.'/'.end($this->name)[1], $output);
        
        if (count($this->data) > 0 && is_array($this->data)) {
            return 1;
        } 

        $zip = new \ZipArchive();
        $destination = $baseName.'-'.date('YmdHis').'.zip';

        if($zip->open($this->path.'/'.$destination, \ZIPARCHIVE::CREATE) !== true) {
            return false;
        }

        foreach($this->name as $file) {
            $zip->addFile($this->path.'/'.$file[1], $baseName.'-'.$file[0].'.pdf');
        }

        $zip->close();
        foreach($this->name as $file) {
            unlink($this->path.'/'.$file[1]);
        }

        //header("Location: ". (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'].'/reporte/'.$destination);

        header("Content-Type: application/zip");
        header("Content-Transfer-Encoding: Binary");
        header("Content-Length: ".filesize($this->path.'/'.$destination));
        header("Content-Disposition: attachment; filename=\"".basename($destination)."\"");
        readfile($this->path.'/'.$destination);
        exit;
    }

    public function generateHtml()
    {
        $html = $this->getWatermark();
        $cantPage = 1;

        if ($this->currentPage == 1 && !empty($this->contentTemplate['header'])) {
            $html .= $this->contentTemplate['header'];
        }

        $max = $this->getMax();
        $cant = count($this->data);
        if ($cant > 0 && is_array($this->data)) {
            //var_dump($max);

            for ($i=0; $i < $cant; $i++) { 
                if (empty($this->data)) {
                    break;
                }

                if ($i == 0 && !empty($this->contentTemplate['style'])) {
                    $html .= $this->contentTemplate['style'];
                }
                //echo $i .' - '.$this->currentRow. ' - '.count($this->data). ' - '. $cant."\n";
                $row = $this->data[$this->currentRow];

                if ($i == 0) {
                    $html .= '<'.$this->elementEmbed.' style="'.$this->elementStyle.'">';
                }
                
                $params = [
                    'row' => $row,
                    'data' => $this->data,
                    'numRowPage' => $i,
                    'currentRow' => $this->currentRow,
                    'currentPage' => $this->currentPage,
                ];

                $function = $this->functionAddRow;
                if (!empty($function) && !empty($this->templateAddRow) && $function($params)) {
                    $html .= $this->getView($this->templateAddRow, $params, null, false);
                    $cant++;                    
                } else {
                    $html .= $this->getBody($params);
                    unset($this->data[$this->currentRow]);
                    $this->currentRow++;
                }

                if ($i == $max - 1 && !empty($this->data)) {
                    $this->currentPage++;

                    if ($cantPage == $this->options['MAX_PAGE_FOR_FILE']) {
                        //echo '***************';
                        return $html.'</'.$this->elementEmbed.'>';
                    }
                    $i = -1;
                    $cantPage++;
                    $max = $this->getMax();
                    $html .= $this->getPageBreak();
                    //echo '-------------';
                }
            }

            $html .= '</'.$this->elementEmbed.'>';
        }


        if (!empty($this->contentTemplate['footer'])) {
            $html .= $this->contentTemplate['footer'];

        }

        //echo '¿¿¿¿¿¿¿¿¿¿¿¿¿¿';
        return $html;
    }

    public function setData($data, $fields = [])
    {
        $this->data = $data;
        $this->options['FIELDS'] = $fields;
    }

    public function setHeader($template, $data = [])
    {
        $this->getView($template, $data, 'header');
    }

    public function setFooter($template, $data = [])
    {
        $this->getView($template, $data, 'footer');
    }

    public function setStyle($template, $data = [])
    {
        $this->getView($template, $data, 'style');
    }

    public function setWatermark($template = '', $data = [])
    {
        $template = empty($template) ? $this->templateWatermark : $template;
        
        $html = $this->getView($template, $data, 'watermark');
        if (!empty($data['pageNumber'])) {
            $html = str_replace('{{ $pageNumber }}', ($this->options['ADD_PAGINATOR'] ? $data['pageNumber'] : ''), $html);
        }

        return $html;
    }

    public function getBody($params)
    {
        if (!empty($this->templateBody)) {
            $html = $this->getView($this->templateBody, $params, null, false);
        } else {
            $html = $this->generateBody($params['row'], $this->options['FIELDS']);
        }

        return $html;
    }

    public function getWatermark()
    {
        if (!empty($this->contentTemplate['watermark'])) {
            $html = $this->contentTemplate['watermark'];
            return str_replace('{{ $pageNumber }}', ($this->options['ADD_PAGINATOR'] ? $this->currentPage : ''), $html);
        }
        
    }

    protected function getPageBreak()
    {
        return '</'.$this->elementEmbed.'><div style="page-break-after: always;"></div>'.$this->getWatermark();
    }

    protected function generateBody($row, $fields)
    {
        $html = '<tr>';
        foreach ($fields as $field) {
            $attr = '';
            $key  = !empty($field['field']) ? $field['field'] : (string) $field;
            $value = $row[$key];

            if (!empty($field['length'])) {
                $value = self::strLimit($value, $field['length']);
            }

            if (!empty($field['attr'])) {
                array_walk($field['attr'], function($value, $key) use (&$attr) {
                    $attr .= $key.'="'.$value.'" ';
                });
            }
            $html .= '<td '.$attr.'>'.$value.'</td>';
        }
        $html .= '</tr>';
        return $html;
    }

    protected function getView($template, $data = [], $name, $cache = true)
    {
        $name = empty($name) ? $template : $name;
        if (empty($this->contentTemplate[$name]) || !$cache) {
            $this->view->setTemplate($template)
                ->setVariables($data);

            $this->contentTemplate[$name] = $this->serviceLocator
                ->get('viewrenderer')
                ->render($this->view);
        }

        return $this->contentTemplate[$name];
    }

    public function getMax()
    {
        if ($this->currentPage == 1 && !empty($this->options['MAX_ROW_FIRST_PAGE'])) {
            $max = $this->options['MAX_ROW_FIRST_PAGE'];
        } elseif ($this->currentPage != 1 && !empty($this->options['MAX_ROW_PAGE'])) {
            $max = $this->options['MAX_ROW_PAGE'];
        } else {
            $max = count($this->data);
        }

        return $max;
    }

    static function strLimit($value, $limit = 100, $end = '...')
    {
        if (empty($value)) {
            return $value;
        }

        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')).$end;
    }
}