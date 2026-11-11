<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Sourcing\Application\Import\Exception\PricingMatrixCsvImportFailed;
use App\Yoowii\Sourcing\Application\Import\PrintPricingMatrixCsvImporter;
use App\Yoowii\Sourcing\Application\Validation\PricingMatrixActivationPolicy;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\PricingMatrixImportData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\PricingMatrixImportType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PricingMatrixController extends AbstractController
{
    #[Route('/matrices/import', name: 'yoowii_admin_sourcing_matrix_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        EntityManagerInterface $entityManager,
        BuiltInPrintProductDefinitionRegistry $definitions,
        PrintPricingMatrixCsvImporter $importer,
        PricingMatrixActivationPolicy $activationPolicy,
    ): Response {
        $data = new PricingMatrixImportData();
        $data->effectiveFrom = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $form = $this->createForm(PricingMatrixImportType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $supplierProduct = $data->supplierProduct ?? throw new \LogicException('Validated supplier product is missing.');
            $effectiveFrom = $data->effectiveFrom ?? throw new \LogicException('Validated effective date is missing.');
            $file = $data->file ?? throw new \LogicException('Validated CSV file is missing.');
            $existing = $entityManager->getRepository(SupplierPricingMatrixVersion::class)->findOneBy([
                'supplierProduct' => $supplierProduct,
                'version' => $data->version,
            ]);

            if (null !== $existing) {
                $form->get('version')->addError(new FormError('Cette version existe déjà pour cette référence.'));

                return $this->renderImportForm($form);
            }

            $csv = file_get_contents($file->getPathname());

            if (false === $csv) {
                $form->get('file')->addError(new FormError('Le fichier temporaire ne peut pas être lu.'));

                return $this->renderImportForm($form);
            }

            $importedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $importedRows = 0;

            try {
                $result = $importer->import(
                    $definitions->get($data->productCode),
                    $supplierProduct,
                    $data->version,
                    strtoupper($data->currencyCode),
                    $effectiveFrom,
                    $importedAt,
                    $csv,
                );

                if ($data->activate) {
                    if ($this->hasActiveConflict($entityManager, $activationPolicy, $result->matrix())) {
                        $form->get('activate')->addError(new FormError(
                            'Une matrice active possède déjà la même date d’effet pour ce produit, cette référence et cette devise.',
                        ));

                        return $this->renderImportForm($form);
                    }

                    $result->matrix()->activate($importedAt);
                }

                $entityManager->persist($result->matrix());
                $entityManager->flush();
                $importedRows = $result->importedRows();
            } catch (PricingMatrixCsvImportFailed $exception) {
                foreach ($exception->errors() as $error) {
                    $form->get('file')->addError(new FormError($error));
                }

                return $this->renderImportForm($form);
            } catch (UniqueConstraintViolationException) {
                $form->get('version')->addError(new FormError('Cette version vient d’être créée par une autre opération.'));

                return $this->renderImportForm($form);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return $this->renderImportForm($form);
            }

            $this->addFlash('success', sprintf('%d tarifs ont été importés.', $importedRows));

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->renderImportForm($form);
    }

    #[Route('/matrices/{id}/activate', name: 'yoowii_admin_sourcing_matrix_activate', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function activate(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        PricingMatrixActivationPolicy $activationPolicy,
    ): Response {
        $matrix = $this->matrix($entityManager, $id);
        $this->assertCsrf($csrfTokenManager, $request, sprintf('activate_matrix_%d', $id));

        if ($this->hasActiveConflict($entityManager, $activationPolicy, $matrix)) {
            $this->addFlash('danger', 'Une matrice active utilise déjà cette date d’effet, ce produit, cette référence et cette devise.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        try {
            $matrix->activate(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $entityManager->flush();
            $this->addFlash('success', 'La matrice a été activée.');
        } catch (\DomainException|\InvalidArgumentException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
    }

    #[Route('/matrices/{id}/archive', name: 'yoowii_admin_sourcing_matrix_archive', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function archive(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $matrix = $this->matrix($entityManager, $id);
        $this->assertCsrf($csrfTokenManager, $request, sprintf('archive_matrix_%d', $id));
        $matrix->archive();
        $entityManager->flush();
        $this->addFlash('success', 'La matrice a été archivée définitivement.');

        return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
    }

    private function matrix(EntityManagerInterface $entityManager, int $id): SupplierPricingMatrixVersion
    {
        $matrix = $entityManager->find(SupplierPricingMatrixVersion::class, $id);

        if (!$matrix instanceof SupplierPricingMatrixVersion) {
            throw $this->createNotFoundException();
        }

        return $matrix;
    }

    private function assertCsrf(
        CsrfTokenManagerInterface $csrfTokenManager,
        Request $request,
        string $tokenId,
    ): void {
        $token = new CsrfToken($tokenId, (string) $request->request->get('_token'));

        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function renderImportForm(FormInterface $form): Response
    {
        return $this->render('admin/sourcing/form.html.twig', [
            'page_title' => 'Importer une matrice tarifaire',
            'form' => $form,
            'back_route' => 'yoowii_admin_sourcing_dashboard',
        ]);
    }

    private function hasActiveConflict(
        EntityManagerInterface $entityManager,
        PricingMatrixActivationPolicy $activationPolicy,
        SupplierPricingMatrixVersion $matrix,
    ): bool {
        /** @var list<SupplierPricingMatrixVersion> $candidates */
        $candidates = $entityManager->getRepository(SupplierPricingMatrixVersion::class)->findBy([
            'supplierProduct' => $matrix->supplierProduct(),
            'currencyCode' => $matrix->currencyCode(),
            'effectiveFrom' => $matrix->effectiveFrom(),
            'status' => PricingMatrixStatus::Active,
        ]);

        return $activationPolicy->hasConflict($candidates, $matrix);
    }
}
