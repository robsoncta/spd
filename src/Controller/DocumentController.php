<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Psr\Log\LoggerInterface;
use OpenApi\Annotations as OA;

class DocumentController extends AbstractController
{
    /**
     * @OA\Post(
     *     path="/api/document/upload",
     *     summary="Upload de documento",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="document",
     *                     description="Arquivo de documento para upload",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resultado do upload e processamento OCR",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="filename", type="string"),
     *             @OA\Property(property="uploadTime", type="number"),
     *             @OA\Property(property="ocrText", type="string"),
     *             @OA\Property(property="ocrTime", type="number"),
     *             @OA\Property(property="filePath", type="string"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    #[Route('/', name: 'homepage')]
    #[Route('/api/document/upload', name: 'document_upload')]
    public function upload(Request $request, LoggerInterface $logger): Response
    {
        $result = null;

        if ($request->isMethod('POST')) {
            /** @var UploadedFile $file */
            $file = $request->files->get('document');
            if ($file) {
                $startTime = microtime(true);
                $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '-' . uniqid() . '.' . $file->guessExtension();
                try {
                    $destination = $this->getParameter('kernel.project_dir') . '/public/uploads';
                    $file->move($destination, $filename);
                    $uploadTime = microtime(true) - $startTime;

                    // Processamento OCR
                    $ocrStartTime = microtime(true);
                    $ocrText = (new TesseractOCR($destination . '/' . $filename))->run();
                    $ocrTime = microtime(true) - $ocrStartTime;

                    $result = [
                        'success' => true,
                        'filename' => $filename,
                        'uploadTime' => $uploadTime,
                        'ocrText' => $ocrText,
                        'ocrTime' => $ocrTime,
                        'filePath' => '/uploads/' . $filename,
                    ];

                    // Logging result
                    $logger->info('Upload and OCR processing successful', $result);
                } catch (FileException $e) {
                    $result = [
                        'success' => false,
                        'message' => 'Erro ao mover o arquivo: ' . $e->getMessage(),
                    ];

                    // Logging error
                    $logger->error('FileException', ['exception' => $e]);
                } catch (\Exception $e) {
                    $result = [
                        'success' => false,
                        'message' => 'Erro no processo de OCR: ' . $e->getMessage(),
                    ];

                    // Logging error
                    $logger->error('Exception', ['exception' => $e]);
                }
            } else {
                $result = [
                    'success' => false,
                    'message' => 'Nenhum arquivo enviado ou arquivo inválido.',
                ];

                // Logging error
                $logger->error('No file uploaded or invalid file');
            }
        }

        return $this->render('document/upload.html.twig', [
            'result' => $result,
        ]);
    }
}
