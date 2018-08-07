<?php declare(strict_types=1);

namespace CryptoSim\Simulation\Presentation;

use CryptoSim\Simulation\Application\SaveTransaction;
use CryptoSim\Simulation\Application\SaveTransactionHandler;
use CryptoSim\Simulation\Domain\GetCryptocurrenciesQuery;
use CryptoSim\Simulation\Domain\PortfolioRepository;
use CryptoSim\Simulation\Domain\Transaction;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use CryptoSim\Framework\Rendering\TemplateRenderer;

final class SimulationController
{
    private $templateRenderer;
    private $portfolioRepository;
    private $getCryptocurrenciesQuery;
    private $saveTransactionHandler;

    public function __construct(
        TemplateRenderer $templateRenderer,
        PortfolioRepository $portfolioRepository,
        GetCryptocurrenciesQuery $getCryptocurrenciesQuery,
        SaveTransactionHandler $saveTransactionHandler
    ) {
        $this->templateRenderer = $templateRenderer;
        $this->portfolioRepository = $portfolioRepository;
        $this->getCryptocurrenciesQuery = $getCryptocurrenciesQuery;
        $this->saveTransactionHandler = $saveTransactionHandler;
    }

    public function show(Request $request, array $vars) {
        $portfolioId = $vars['portfolioId'];
        $template = 'Simulation.html.twig';

        //TODO - Validate the $portfolioId once you get the portfolio data from database
        $portfolio = $this->portfolioRepository->getPortfolioFromId($portfolioId);
        $cryptocurrencies = $this->getCryptocurrenciesQuery->execute();

        if(!$portfolio) {
            $template = 'PageNotFound.html.twig';
        }

        $content = $this->templateRenderer->render($template, [
            'portfolioId' => $portfolioId,
            'portfolio' => $portfolio,
            'cryptocurrencies' => $cryptocurrencies
        ]);
        return new Response($content);
    }

    public function saveTransaction(Request $request, array $vars)
    {
        $portfolioId = $vars['portfolioId'];
        $response = new RedirectResponse("/play/{$portfolioId}");

        $saveTransaction = new SaveTransaction(
            $portfolioId,
            (int)$request->get('cryptocurrency-id'),
            (string)$request->get('buy-amount'),
            (string)$request->get('type')
        );
        $this->saveTransactionHandler->handle($saveTransaction);

        // TODO - Add error handling before returning response
        return $response;
    }
}