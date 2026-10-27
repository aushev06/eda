/** Connection to the local terminal agent (deploy/sber-terminal), from POS page props. */
export type CardTerminalConfig = {
    url: string;
    token: string;
};

export type TerminalResult =
    | { status: 'approved'; message: string }
    | { status: 'declined'; message: string }
    | { status: 'unreachable'; message: string };

/** A customer can take a while at the terminal; Pilot itself times out sooner. */
const PAYMENT_TIMEOUT_MS = 180_000;

/**
 * Send a card payment to the bank terminal and wait for the bank's answer.
 * Never throws: an agent that is not running comes back as "unreachable",
 * so the cashier can fall back to keying the amount in by hand.
 */
export async function chargeCard(
    config: CardTerminalConfig,
    amount: number,
): Promise<TerminalResult> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), PAYMENT_TIMEOUT_MS);

    try {
        const response = await fetch(`${config.url}/pay`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Terminal-Token': config.token,
            },
            body: JSON.stringify({ amount }),
            signal: controller.signal,
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            return {
                status: 'unreachable',
                message:
                    data.error ??
                    `Программа связи ответила ошибкой ${response.status}`,
            };
        }

        return data.approved
            ? { status: 'approved', message: data.message || 'Одобрено' }
            : {
                  status: 'declined',
                  message: data.message || 'Операция отклонена',
              };
    } catch {
        return {
            status: 'unreachable',
            message: controller.signal.aborted
                ? 'Терминал не ответил вовремя — проверьте результат на экране терминала'
                : 'Программа связи с терминалом не запущена',
        };
    } finally {
        clearTimeout(timer);
    }
}
