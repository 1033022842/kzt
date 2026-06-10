import request from '../utils/request'

export function getBalance() {
  return request.get('/payment/balance')
}

export function getTrc20Address() {
  return request.get('/payment/trc20-address')
}

export function createRechargeOrder(amount) {
  return request.post('/payment/recharge', { amount })
}

export function getRechargeRecords() {
  return request.get('/payment/recharge-records')
}

export function purchase(data) {
  return request.post('/payment/purchase', data)
}

export function getPurchaseRecords() {
  return request.get('/payment/purchase-records')
}
