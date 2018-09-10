<?php declare(strict_types=1);

namespace CryptoSim\Simulation\Infrastructure;

use CryptoSim\Simulation\Application\OwnedCryptocurrency;
use CryptoSim\Simulation\Application\Portfolio;
use CryptoSim\Simulation\Domain\Currency;
use CryptoSim\Simulation\Domain\PortfolioRepository;
use Doctrine\DBAL\Connection;

final class DbalPortfolioRepository implements PortfolioRepository
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getPortfolioFromId(string $portfolioId): Portfolio
    {
        $qb = $this->connection->createQueryBuilder();
        $stmt = $qb
            ->addSelect('id')
            ->addSelect('title')
            ->from('portfolios')
            ->where("id = {$qb->createNamedParameter($portfolioId)}")
            ->execute()
        ;

        $row = $stmt->fetch();

        if(!$row) {
            return null;
        }

        $portfolioUSDAmount = $this->getPortfolioUSDAmountFromId($portfolioId);
        $portfolioCryptoWorthInUSD = $this->getPortfolioCryptoWorthInUSDFromId($portfolioId);

        // TODO - Maybe use dependency injection instead of creating these objects
        $portfolioUSDAmountCurrency = new Currency($portfolioUSDAmount);
        $portfolioCryptoWorthInUSDCurrency = new Currency($portfolioCryptoWorthInUSD);

        $portfolioWorth = $portfolioUSDAmountCurrency->add($portfolioCryptoWorthInUSDCurrency, Currency::USD_FRACTION_DIGITS);
        $cryptocurrencies = $this->getCryptocurrenciesFromPortfolioId($portfolioId);

        return new Portfolio(
            $row['id'],
            $row['title'],
            $portfolioUSDAmount,
            $portfolioCryptoWorthInUSD,
            $portfolioWorth,
            $cryptocurrencies
        );
    }

    /**
     * @param string $portfolioId
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getCryptocurrenciesFromPortfolioId(string $portfolioId): array
    {
        $stmt = $this->connection->prepare("
            SELECT
              t.portfolio_id,
              c.abbreviation,
              c.name,
              c.id,
              CAST(SUM(t.cryptocurrency_amount*c.worth_in_USD) AS DECIMAL(17,2)) AS crypto_worth,
              CAST(SUM(t.cryptocurrency_amount) AS DECIMAL(17,8)) AS quantity
            FROM transactions t 
            LEFT JOIN cryptocurrencies c ON t.cryptocurrency_id = c.id 
            WHERE 
              t.portfolio_id = :portfolioId
              AND status = 'active' 
            GROUP BY c.id
        ");
        $stmt->bindParam(':portfolioId', $portfolioId);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $cryptocurrencies = [];

        // TODO - Error handling

        if(!$rows) {
            return [];
        }

        foreach ($rows as $row){
            $cryptocurrencies[] = new OwnedCryptocurrency(
                $row['id'],
                $row['name'],
                $row['abbreviation'],
                $row['crypto_worth'],
                $row['quantity']
            );
        }

        return $cryptocurrencies;
    }
    // TODO - Instead of just mention 'FromId' maybe say 'FromPortfolioId'
    private function getPortfolioCryptoWorthInUSDFromId(string $portfolioId): ?string
    {
        // TODO - To prevent floating point errors, test out doing the math operations out of SQL
        $stmt = $this->connection->prepare("
            SELECT
              CAST(SUM(t.cryptocurrency_amount*c.worth_in_USD) AS DECIMAL(17,2)) AS crypto_worth,
              p.start_amount
            FROM portfolios p
            LEFT JOIN transactions t ON p.id = t.portfolio_id
            LEFT JOIN cryptocurrencies c on t.cryptocurrency_id = c.id
            WHERE 
              t.portfolio_id = :portfolioId1
              AND t.status = 'active'
         ");
        $stmt->bindParam(':portfolioId1', $portfolioId);
        $stmt->execute();

        $row =$stmt->fetch();

        if(!$row) {
            return null; // TODO - Add error catching for when this returns NULL
        }
        if(!$row['crypto_worth'] && $row['start_amount']) {
            return '0'; // no transactions, but portfolio exists
        }

        return $row['crypto_worth'];
    }


    private function getPortfolioUSDAmountFromId(string $portfolioId): ?string
    {
        // TODO - Make these queries readable
        $stmt = $this->connection->prepare("
          SELECT
            p.start_amount,
            CAST((p.start_amount + SUM(t.usd_amount)) AS DECIMAL(17,2)) AS usd_amount
          FROM portfolios p
          LEFT JOIN transactions t ON p.id = t.portfolio_id
          LEFT JOIN cryptocurrencies c ON t.cryptocurrency_id = c.id
          WHERE 
            t.portfolio_id = :portfolioId1 
            AND t.status='active'
        ");
        $stmt->bindParam(':portfolioId1', $portfolioId);
        $stmt->execute();

        $row =$stmt->fetch();

        if(!$row) {
            return null;// TODO - Add error catching for when this returns NULL
        }
        if(!$row['usd_amount'] && $row['start_amount']) {
            return $row['start_amount'];
        }

        return $row['usd_amount'];
    }
}