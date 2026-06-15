<?php

declare(strict_types=1);

namespace CityBus\Controllers\Crm;

use CityBus\Controllers\Controller;
use CityBus\Core\Database;
use CityBus\Core\Request;
use CityBus\Services\FeedbackService;

final class FeedbackController extends Controller
{
    /** Page publique (token URL) pour soumettre un avis. */
    public function showPublicForm(Request $request, string $token): void
    {
        $row = (new FeedbackService())->findByToken($token);
        if (!$row) {
            http_response_code(404);
            $this->view('errors/404');
            return;
        }
        $this->view('crm/feedback/public_form', [
            'title' => 'Notez votre voyage',
            'feedback' => $row,
            'token'    => $token,
        ]);
    }

    public function submitPublic(Request $request, string $token): void
    {
        $svc = new FeedbackService();
        $ok = $svc->submit($token, $request->all());
        if (!$ok) {
            $this->flash('warning', 'Avis déjà soumis ou invalide.');
        } else {
            $this->flash('success', 'Merci pour votre avis !');
        }
        redirect('feedback/' . $token . '/thanks');
    }

    public function thanks(Request $request, string $token): void
    {
        $this->view('crm/feedback/thanks', ['title' => 'Merci']);
    }

    /** Dashboard admin/CRM des avis. */
    public function index(Request $request): void
    {
        $from = trim((string)$request->input('from', ''));
        $to   = trim((string)$request->input('to',   ''));
        $svc  = new FeedbackService();
        $summary = $svc->summary($from ?: date('Y-m-01'), $to ?: date('Y-m-d'));

        $feedbackWhere  = ['cf.submitted_at IS NOT NULL'];
        $feedbackParams = [];
        if ($from !== '') { $feedbackWhere[] = 'DATE(cf.submitted_at) >= ?'; $feedbackParams[] = $from; }
        if ($to   !== '') { $feedbackWhere[] = 'DATE(cf.submitted_at) <= ?'; $feedbackParams[] = $to; }
        $feedbackSql = implode(' AND ', $feedbackWhere);

        $recent  = Database::select(
            "SELECT cf.*, t.ticket_number, tr.trip_code, c.first_name, c.last_name
             FROM customer_feedback cf
             LEFT JOIN tickets t ON t.id = cf.ticket_id
             LEFT JOIN trips tr ON tr.id = cf.trip_id
             LEFT JOIN customers c ON c.id = cf.customer_id
             WHERE $feedbackSql
             ORDER BY cf.submitted_at DESC LIMIT 30",
            $feedbackParams
        );

        $this->view('crm/feedback/dashboard', [
            'title'   => 'Avis clients',
            'summary' => $summary,
            'recent'  => $recent,
            'from'    => $from, 'to' => $to,
        ]);
    }
}
