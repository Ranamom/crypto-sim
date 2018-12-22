import {Update} from "../../Models/Simulation/Update";
import {Leaderboard} from "../../Models/Simulation/Leaderboard";
import * as simulationView from "../../Views/Simulation/SimulationView";
import {IdNames, classNames, elements, api} from "../../Views/Simulation/base";

export var state = {
    buyDataTable : null,
    sellDataTable : null,
    updateModel : new Update(),
    leaderboard : new Leaderboard(),
    leaderboardDataTable : null,
    portfolioGroupId : null,
    groupLeaderboardDataTable : null
};

$(document).ready( async function () {
    // Initialize state
    initializePortfolioGroupIdState();

    // Initialize
    initializeGoldenLayout();
    initializeBuyCryptoDataTable();
    initializeWebsocketConn();
    initializeSellCryptoDataTable();
    initializeLeaderboardTable();

    const updatedPortfolio = await Update.updatePortfolio();

    simulationView.updatePortfolioInfo(updatedPortfolio);
} );

function initializePortfolioGroupIdState() {
    state.portfolioGroupId = document.querySelector('#group-id').value;
}

// TODO - Look into downloading this using npm instead of using cdn
function initializeGoldenLayout() {
    const buyCryptoTableHTML = simulationView.getBuyCryptoTable();
    const sellCryptoTableHTML = simulationView.getSellCryptoTable();
    const portfolioInfo = simulationView.getPortfolioInfo();
    const leaderboardButton = simulationView.getLeaderboardButton();

    var config = {
        content: [{
            type: 'row',
            content: [
                {
                    type:'component',
                    componentName: 'Buy',
                    componentState: { text: leaderboardButton + buyCryptoTableHTML}
                },
                {
                    type:'component',
                    componentName: 'Sell',
                    componentState: { text: portfolioInfo + sellCryptoTableHTML }
                }
            ]
        }]
    };
    var myLayout = new GoldenLayout( config );

    myLayout.registerComponent( 'Buy', function( container, componentState ){
        container.getElement().html(componentState.text);
    });
    myLayout.registerComponent( 'Sell', function( container, componentState ){
        container.getElement().html(componentState.text);
    });

    myLayout.init();
}

// TODO - Consider using npm version of jquery over cdn (look into pros and cons)
// TODO - Consider using npm version of datatables over cdn (look into pros and cons)
function initializeBuyCryptoDataTable() {
    state.buyDataTable = $(`#${IdNames.buyCryptoTable}`).DataTable( {
        "ajax" : {
            "url": api.getBuyCryptoData,
            "type": "POST",
            "dataSrc": ""
        },
        "columns" : [
            {
                className:      'details-control',
                orderable:      false,
                data:           null,
                defaultContent: '<button class="btn btn-primary btn-sm">Buy</button>'
            },
            { "data": "percent_change" },
            { "data": "name" },
            { "data": "abbreviation" },
            { "data": "worth_in_USD" }
        ],
        "createdRow": function(row, data) {
            $(row).data('id', data.id);
        }
    });

    initializeDetailsControlEventListener(state.buyDataTable, IdNames.buyCryptoTable);
}

async function initializeSellCryptoDataTable() {
    const initialPortfolio = await state.updateModel.getPortfolio();

    state.sellDataTable = $(`#${IdNames.sellCryptoTable}`).DataTable({
        "data": initialPortfolio.cryptocurrencies,
        "columns" : [
            {
                className:      'details-control',
                orderable:      false,
                data:           null,
                defaultContent: '<button class="btn btn-success btn-sm">Sell</button>'
            },
            { "data": "percentChange" },
            { "data": "name" },
            { "data": "abbreviation" },
            { "data": "worthInUSD" },
            { "data": "quantity" },
        ],
        "createdRow": function(row, data) {
            $(row).data('id', data.id);
        }
    });

    initializeDetailsControlEventListener(state.sellDataTable, IdNames.sellCryptoTable);
}

function initializeDetailsControlEventListener(dataTable, tableId) {
    $(`#${tableId} tbody`).on('click', 'td.details-control', function () {
        const cryptoId = $(this.parentElement).data('id');
        simulationView.renderChildRow(this, dataTable, tableId, cryptoId);
        initializeSubmitTransactionEventListener();
    } );
}

function initializeSubmitTransactionEventListener() {
    $('.transaction-wrapper').on('click', '.submit-transaction-btn', async function() {
        const type = simulationView.getTransactionType(this);
        const transactionAmount = simulationView.getTransactionAmount(this);
        const cryptocurrencyId = simulationView.getCryptocurrencyId(this);

        const results = await state.updateModel.saveTransaction(type, transactionAmount, cryptocurrencyId);
        // TODO - This code is copy pasted in some places, turn it into an object that can be used everywhere
        const portfolioData = {
            totalUSDAmount : results.updatedPortfolio.USDAmount,
            cryptoWorthInUSD : results.updatedPortfolio.cryptoWorthInUSD,
            cryptocurrencies : results.updatedPortfolio.cryptocurrencies,
            portfolioID : results.updatedPortfolio.id,
            portfolioWorth : results.updatedPortfolio.portfolioWorth,
            title : results.updatedPortfolio.title,
            portfolioHTML : results.content
        };
        simulationView.repopulateSellCryptoTable(portfolioData);
        simulationView.updatePortfolioInfo(portfolioData);
    });
}

function initializeLeaderboardButtonEventListener() {
    $('#leaderboard-button button').on('click', async function() {
        if(state.portfolioGroupId !== '') {
            // portfolio is part of a group, get group leaderboard
            const groupLeaderboardData = await state.leaderboard.getGroupLeaderboardData(state.portfolioGroupId);
            simulationView.renderLeaderboard(state.groupLeaderboardDataTable, groupLeaderboardData);
        }
        const leaderboardData = await state.leaderboard.getLeaderboardData();

        simulationView.renderLeaderboard(state.leaderboardDataTable, leaderboardData);
    });
}

async function initializeLeaderboardTable() {
    if(state.portfolioGroupId !== undefined) {
        // portfolio is part of a group, get group leaderboard
        const groupLeaderboardData = await state.leaderboard.getGroupLeaderboardData(state.portfolioGroupId);
        state.groupLeaderboardDataTable = $(`#group-leaderboard-table`).DataTable( {
            "data": groupLeaderboardData.leaderboardEntries,
            "columns" : [
                { "data": "position" },
                { "data": "username" },
                { "data": "portfolioName" },
                { "data": "portfolioWorth" }
            ]
        });
    }

    const leaderboardData = await state.leaderboard.getLeaderboardData();

    state.leaderboardDataTable = $(`#${IdNames.leaderboardTable}`).DataTable( {
        "data": leaderboardData.leaderboardEntries,
        "columns" : [
            { "data": "position" },
            { "data": "username" },
            { "data": "portfolioName" },
            { "data": "portfolioWorth" }
        ]
    });

    initializeLeaderboardButtonEventListener();
}

function initializeWebsocketConn() {
    var conn = new ab.Session('ws://localhost:8079',
        function() {
            conn.subscribe('cryptoData', async function (topic, cryptoData) {
                // render cryptocurrencies to buy
                simulationView.repopulateBuyCryptoTable(cryptoData, state.buyDataTable);

                // get updated portfolio
                const updatedPortfolio = await Update.updatePortfolio();

                // render updated portfolio
                simulationView.updatePortfolio(updatedPortfolio);
                simulationView.updatePortfolioInfo(updatedPortfolio);
            });
        },
        function() {
            console.warn('WebSocket connection closed');
        },
        {'skipSubprotocolCheck': true}
    );
}
