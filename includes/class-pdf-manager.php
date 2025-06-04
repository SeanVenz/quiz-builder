<?php
/**
 * PDF Library Manager for Quiz Builder
 * Handles different PDF generation libraries and provides fallbacks
 */

if (!defined('ABSPATH')) {
    exit;
}

class QB_PDF_Manager {
    
    private static $dompdf_available = false;
    private static $tcpdf_available = false;
      /**
     * Initialize PDF libraries
     */
    public static function init() {
        // Get the base path
        $base_path = defined('QB_PATH') ? QB_PATH : dirname(__DIR__) . '/';
        
        // Check for DomPDF
        if (file_exists($base_path . 'vendor/dompdf/dompdf/autoload.inc.php')) {
            require_once $base_path . 'vendor/dompdf/dompdf/autoload.inc.php';
            self::$dompdf_available = class_exists('Dompdf\Dompdf');
        }
        
        // Check for TCPDF
        if (file_exists($base_path . 'vendor/tecnickcom/tcpdf/tcpdf.php')) {
            require_once $base_path . 'vendor/tecnickcom/tcpdf/tcpdf.php';
            self::$tcpdf_available = class_exists('TCPDF');
        }
    }
    
    /**
     * Generate PDF using the best available method
     */
    public static function generate_pdf($html_content, $filename, $title = '') {
        self::init();
        
        if (self::$dompdf_available) {
            return self::generate_with_dompdf($html_content, $filename, $title);
        } elseif (self::$tcpdf_available) {
            return self::generate_with_tcpdf($html_content, $filename, $title);
        } else {
            return self::generate_with_browser_pdf($html_content, $filename, $title);
        }
    }
    
    /**
     * Generate PDF using DomPDF
     */
    private static function generate_with_dompdf($html_content, $filename, $title) {
        try {
            $dompdf = new \Dompdf\Dompdf([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Arial'
            ]);
            
            $dompdf->loadHtml($html_content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            // Output PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
            
            echo $dompdf->output();
            exit;
            
        } catch (Exception $e) {
            error_log('DomPDF Error: ' . $e->getMessage());
            return self::generate_with_browser_pdf($html_content, $filename, $title);
        }
    }
    
    /**
     * Generate PDF using TCPDF
     */
    private static function generate_with_tcpdf($html_content, $filename, $title) {
        try {
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('Quiz Builder Plugin');
            $pdf->SetAuthor('WordPress Quiz Builder');
            $pdf->SetTitle($title);
            $pdf->SetSubject('Quiz Results');
            
            // Set margins
            $pdf->SetMargins(15, 20, 15);
            $pdf->SetAutoPageBreak(TRUE, 20);
            
            // Add a page
            $pdf->AddPage();
            
            // Write HTML content
            $pdf->writeHTML($html_content, true, false, true, false, '');
            
            // Output PDF
            $pdf->Output($filename, 'D');
            exit;
            
        } catch (Exception $e) {
            error_log('TCPDF Error: ' . $e->getMessage());
            return self::generate_with_browser_pdf($html_content, $filename, $title);
        }
    }
      /**
     * Generate PDF using browser's print-to-PDF functionality
     * This creates a special HTML page optimized for PDF printing
     */
    private static function generate_with_browser_pdf($html_content, $filename, $title) {
        // Add print-specific styles and JavaScript
        $print_optimized_html = self::optimize_html_for_pdf($html_content, $title);
        
        // Set headers for HTML response that will guide users to save as PDF
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . str_replace('.pdf', '.html', $filename) . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        echo $print_optimized_html;
        exit;
    }/**
     * Optimize HTML for PDF printing
     */
    public static function optimize_html_for_pdf($html_content, $title) {        // Enhanced CSS for better PDF printing
        $pdf_styles = '
        <style>
            @page {
                size: A4;
                margin: 1in;
            }
            
            @media print {
                body {
                    -webkit-print-color-adjust: exact !important;
                    color-adjust: exact !important;
                    font-size: 12pt;
                    line-height: 1.4;
                }
                
                .header {
                    page-break-after: avoid;
                }
                
                .answers-table {
                    page-break-inside: avoid;
                }
                
                .answers-table tr {
                    page-break-inside: avoid;
                }
                
                .pdf-instructions,
                .quiz-pdf-export,
                .no-print {
                    display: none !important;
                }
                
                .correct-answer {
                    background-color: #d4edda !important;
                }
                
                .incorrect-answer {
                    background-color: #f8d7da !important;
                }
            }
            
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #333;
            }
            
            .pdf-instructions {
                background: #e7f3ff;
                border: 2px solid #0066cc;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
                font-family: Arial, sans-serif;
            }
            
            .pdf-instructions h3 {
                margin-top: 0;
                color: #0066cc;
                font-size: 18px;
            }
            
            .pdf-instructions .steps {
                background: white;
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            
            .pdf-instructions .step {
                margin: 10px 0;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            
            .pdf-instructions .step:last-child {
                border-bottom: none;
            }
            
            .pdf-instructions .step strong {
                color: #0066cc;
            }
            
            @media screen {
                .pdf-instructions {
                    display: block;
                }
            }
            
            @media print {
                .pdf-instructions {
                    display: none;
                }
            }
        </style>
        
        <script>
            // Auto-trigger print dialog after page loads
            window.addEventListener("load", function() {
                setTimeout(function() {
                    if (confirm("🎯 Ready to save your quiz results as PDF?\\n\\nClick OK to open the print dialog, then select \\"Save as PDF\\" as your printer.")) {
                        window.print();
                    }
                }, 1500);
            });
        </script>';
        
        // Add detailed instructions for users
        $instructions_html = '
        <div class="pdf-instructions">
            <h3>📄 Save Your Quiz Results as PDF</h3>
            <div class="steps">
                <div class="step">
                    <strong>Step 1:</strong> Press <kbd>Ctrl+P</kbd> (Windows) or <kbd>Cmd+P</kbd> (Mac) to open print dialog
                </div>
                <div class="step">
                    <strong>Step 2:</strong> In the printer selection, choose:
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <li><strong>Chrome/Edge:</strong> "Save as PDF"</li>
                        <li><strong>Firefox:</strong> "Microsoft Print to PDF"</li>
                        <li><strong>Safari:</strong> Click "PDF" button in bottom left</li>
                    </ul>
                </div>
                <div class="step">
                    <strong>Step 3:</strong> Click "Save" and choose your download location
                </div>
            </div>
            <p style="margin-bottom: 0;"><em>💡 This instruction box will not appear in your PDF file!</em></p>
        </div>';
        
        // Insert the styles and instructions into the HTML
        $html_content = str_replace('</head>', $pdf_styles . '</head>', $html_content);
        $html_content = str_replace('<body>', '<body>' . $instructions_html, $html_content);
        
        return $html_content;
    }
    
    /**
     * Check what PDF generation methods are available
     */
    public static function get_available_methods() {
        self::init();
        
        $methods = [];
        if (self::$dompdf_available) {
            $methods[] = 'DomPDF';
        }
        if (self::$tcpdf_available) {
            $methods[] = 'TCPDF';
        }
        $methods[] = 'Browser Print-to-PDF';
        
        return $methods;
    }
}
