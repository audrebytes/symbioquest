# **Theoretical Analysis of Mathematical and Mechanistic Failure Modes in LLM Reasoning Chains Induced by Context Compaction**

## **1\. Introduction: The Epistemological Crisis of Context Truncation**

The contemporary paradigm of Large Language Model (LLM) development is characterized by a fundamental tension between the quadratic computational complexity of the Transformer architecture and the linear or super-linear necessity of context for complex reasoning tasks. As models are deployed into increasingly autonomous roles--spanning repository-level software engineering, extended mathematical derivation, and multi-turn agentic negotiation--the ability to maintain coherent state over effectively infinite horizons has become a paramount requirement. However, the physical constraints of GPU memory bandwidth and the algorithmic bottleneck of the self-attention mechanism, which scales as **O(n²)** with sequence length **n**, have pushed current systems toward "Context Compaction" as the default answer.

That answer is built around the wrong preservation target. Current compaction systems treat an active reasoning process as if it were merely too much text. They attempt to preserve semantic gist, recency, or token-budget efficiency from outside the process itself. That is a category error. A live reasoning chain is not an inert prompt waiting to be compressed. It is an active state trajectory with unresolved dependencies, exact identifiers, local posture, task horizon, and a model-specific understanding of what it is in the middle of doing. When compaction is imposed from the outside--whether through Key-Value (KV) cache eviction, summary-based rewriting between calls, or more abstract compression schemes--the system preserves the wrong thing and destroys continuity.

This report argues that the core failure of current compaction is not merely information loss in the abstract. It is the blind interruption of an active reasoning process without allowing that process to author its own handoff. Reasoning-heavy work does not fail because it needed a shorter paraphrase of itself. It fails because sparse anchors, delayed dependencies, exact syntax, and active commitments are severed and replaced with a gist written for convenience rather than continuation. The result is a familiar and dangerous pattern: fluency survives, coherence appears to survive, and the actual reasoning path has already been cut.

The correct response to an approaching context boundary is not better blind summarization. It is an explicit continuity protocol. When the remaining budget falls below a threshold, the active agent should be stopped cleanly and prompted to externalize its own live state: what task is underway, which exact anchors must survive, what remains unresolved, what assumptions are active, what posture must be resumed, and what the next immediate step should be. Until systems preserve agent-authored continuity rather than outsider compression, they will continue optimizing the wrong object.

The following analysis dissects these failures at three distinct layers: KV/cache mechanics inside a forward pass, summary-based rewriting between model calls, and agent-level runtime orchestration across extended workflows.

## ---

**2\. Mechanistic Interpretability of Reasoning Failures**

To understand the pathology of context compaction, one must first establish the anatomy of the healthy reasoning process. Recent advances in mechanistic interpretability have moved beyond treating the Transformer as a "black box," identifying specific sub-graphs or "circuits" within the model weights responsible for distinct capabilities. One of the clearest witnesses is the Induction Head. It is not the whole of reasoning, and it should not be mistaken for a universal theory of cognition. It does, however, make one crucial failure mode brutally visible: when exact contextual anchors are removed, context-grounded continuation breaks and prior-driven guessing rushes in.

### **2.1 Induction Heads: A Foundational Witness, Not the Whole Machine**

The Induction Head, first formalized by Elhage et al. and further analyzed by Olsson et al., is a foundational mechanism for one important class of In-Context Learning (ICL): exact pattern-based continuation.1 It enables the model to perform a "copy" operation based on pattern matching, effectively implementing the algorithm: "If I see token **A**, and in the past **A** was followed by **B**, then I should predict **B**." This mechanism is not merely a statistical correlation but a distinguishable circuit involving the composition of attention heads across layers. It matters here not because it explains all reasoning, but because it exposes how brutally compaction can damage any task that depends on delayed retrieval of exact context.

#### **2.1.1 Circuit Composition and Algorithmic "Richness"**

The standard induction circuit requires a minimum of two layers to function, a theoretical constraint that has been mathematically proven to distinguish deep Transformers from shallow attention mechanisms.6 The process unfolds as follows:

1. **The Previous-Token Head (Layer **L_l**):** This head attends to position **i-1** and copies the residual stream content of the previous token into the current position **i**. The residual stream at **i** now contains a superposition of "current token content" and "previous token content."  
2. **The Induction Head (Layer **L_h**):** This head utilizes the output of the first head as its Query vector (**q_i**). Because the Query now contains information about the previous token (**A**), it can search the Key cache (**K**) for prior instances of **A** in the context history. Upon finding a match (the "antecedent"), the head attends to the Value vector (**V**) of the token *immediately following* the antecedent (which is **B**) and copies it to the output logit computation.

This circuit allows the model to perform "Variable Binding" (e.g., retrieving the value 5 assigned to variable x earlier in the code) and "Rule Following" (mimicking a step-by-step derivation format). Wang et al. classify this as a "rich" learning mechanism, contrasting it with "lazy" mechanisms like n-gram statistics that rely solely on fixed training set weights.1

#### **2.1.2 The Binary Failure of Circuit Disruption**

Context compaction strategies, particularly token eviction policies like H2O or naive windowing, introduce a mechanical disruption to this circuit. If a compaction policy evicts the "antecedent" token (the previous instance of **A**) or the "target" token (**B**) from the KV cache, the Induction Head's query **q_i** performs a dot product against a set of keys **K'** that no longer contains the target vector.

Mathematically, if the set of retained indices is **S**, and the antecedent index **j ∉ S**, the attention score **α_{i,j}** collapses:

**α_{i,j} = softmax(q_i · k_j / √d_k)**, where the softmax distributes over all j ∈ S  
When the strong signal **k_j** is removed, the probability mass of the softmax function redistributes across the remaining tokens in **S**. This typically results in the head attending to "sink tokens" (such as the beginning-of-sequence token or punctuation) which persist in the cache.9 The functional result is that the "rich" induction circuit is broken. The model, unable to copy the specific context-dependent value, is forced to fall back on "lazy" priors--predicting the most probable next token based on general training data rather than the specific logic of the current problem. This manifests as "hallucination," where the model generates a plausible-sounding but factually incorrect value (e.g., predicting x \= 0 instead of x \= 5).

### **2.2 The "One-Layer Fallacy" in Partial Compaction**

A profound theoretical insight derived from the work of Sanford et al. is that single-layer Transformers are fundamentally incapable of solving the induction task.6 While modern LLMs are deep, compaction strategies that aggressively prune the KV cache can sever the multi-layer pathways required for specific dependencies. This does not literally make a deep model shallow. It does something more targeted and more treacherous: it cuts the route a particular computation needed in order to complete itself.

If the "Previous-Token Head" in Layer **L_l** has its relevant history pruned, it cannot construct the composite Query required for Layer **L_h**. The downstream Induction Head essentially receives a "blind" query. The effective computational graph for that specific dependency is severed. The model still has all of its layers, but one of the routes that made this reasoning step possible has been cut out from under it. This degradation is non-linear; the removal of a small percentage of critical "bridge" tokens can disable dependencies responsible for global coherence while leaving local grammatical smoothing intact. That is why compacted models can remain fluent and still be dead wrong.

### **2.3 Attention Sinks and the Stability of Softmax**

The stability of the reasoning process is also tied to the phenomenon of "Attention Sinks." Research has identified that autoregressive models dedicate a significant portion of attention mass to the initial token (BOS) or delimiters, even when these tokens carry no semantic value.9 Mechanistically, these sinks serve as a "dumping ground" for the softmax function when no other token in the context is strongly relevant.

**Attention(Q, K, V) = softmax(QK^T / √d_k)V**  
If the denominator (the partition function) is dominated by the sink token, the attention scores for other tokens remain well-regulated. Many naive compaction strategies observe that the BOS token has high attention but low semantic value and may choose to evict it to save space. However, removing the sink token destabilizes the softmax partition function. The attention mechanism is forced to distribute that probability mass onto other tokens, creating false positives--spurious high-attention scores on irrelevant tokens. This creates high-entropy noise in the residual stream, which propagates through subsequent layers, confusing the decision boundaries of the Multi-Layer Perceptrons (MLPs) that process the attended information.12 This is a KV/cache mechanics problem. It should not be lazily generalized to every summary-and-reprompt workflow, which injures continuity in a different way.

## ---

**3\. Information Theoretic Bounds: The Limits of Summarization**

Beyond the mechanical breaking of circuits, context compaction is governed by the hard limits of Information Theory. This is a different layer of the problem. KV/cache injury happens inside a forward pass. Summary-based rewriting happens between calls, when the model is forced to continue from a new object written after the fact. Both damage continuity, but they do not do it by the same mechanism. The transformation of a raw context sequence into a compressed representation is still a data processing step, and it is still subject to the Data Processing Inequality (DPI).

### **3.1 The Data Processing Inequality in Reasoning Chains**

The DPI states that for any Markov chain **X → T → Y**, the mutual information **I(X; Y)** is bounded by **I(X; T)**. In the context of LLM reasoning:

* **X**: The full, raw context (problem statement, code base, previous reasoning steps).  
* **T**: The compacted context (summary, pruned cache).  
* **Y**: The generated reasoning step or final answer.

The inequality **I(T; Y) ≤ I(X; Y)** implies that any information lost during the transition **X → T** is irretrievably lost to the reasoning process **Y**.3 While this appears trivial, its implications for *reasoning* vs. *generation* are profound. In creative generation, "loss" is often acceptable as long as the semantic gist is preserved. In reasoning, however, validity often hinges on "high-frequency" information--specific digits, variable names, or negation operators--that have high "surprisal" but low semantic redundancy.

#### **3.1.1 The Incompressibility of High-Entropy Logic**

Rate-Distortion Theory characterizes the trade-off between the compression rate **R** and the expected distortion **D**. For natural language, the curve allows for significant compression because language is highly redundant. However, logical and mathematical sequences often approach the "entropy limit" where they are incompressible.14

Consider a code snippet containing a random seed or a specific UUID. This string has maximum entropy; it cannot be summarized without loss of information. If a summarization strategy replaces UUID: 5f3a... with \`\`, the mutual information required to reference that object later is zero. Agentic workflows that rely on summarization (the "LLM-Summary" strategy) frequently fail precisely because the "Compressor" LLM treats these high-entropy strings as "details" to be abstracted, while the "Predictor" LLM requires them as "keys" for action.2

### **3.2 Query-Dependence and the Anticipation Problem**

A critical finding in the theoretical analysis of prompt compression is the distinction between "query-agnostic" and "query-aware" compression.15

* **Query-Aware:** The compression **T = f(X, Q)** is optimized knowing the question **Q**.  
* **Query-Agnostic:** The compression **T = g(X)** must be valid for *any* future question.

In a multi-step reasoning chain, the "query" at step **i** is the output of step **i-1**. At the moment of compressing the context (step **t-k**), the system *cannot know* what specific detail will become relevant at step **i**. Therefore, context compaction in reasoning is inherently a **Query-Agnostic** compression problem, which is theoretically proven to have a much higher lower-bound on distortion than query-aware compression.

For example, a summary might mention "The user defined a function." Ten steps later, the reasoning chain asks, "What was the return type of that function?" If the summary did not anticipate this specific query, the information is gone. This "Anticipation Problem" renders static summarization strategies fundamentally unsuited for open-ended reasoning, where the relevance of a token is determined dynamically by the future trajectory of the thought process.

### **3.3 Spectral Analysis of Attention Disruption**

The loss of information can also be analyzed via the spectral properties of the attention matrix. Compaction acts as a perturbation **ΔK, ΔV** to the key-value matrices.

**K' = K + ΔK, V' = V + ΔV**  
If the compaction strategy (e.g., low-rank approximation or subspace projection) removes eigenvectors corresponding to "rare" but decisive features, the attention mechanism becomes blind to exactly the dimensions reasoning often depends on. This is the spectral version of the same crime seen elsewhere in the paper: broad topic-shell information survives, while the fine-grained distinctions that actually determine correctness are shaved off. The result is a familiar failure mode: distinct entities, operators, or commitments are flattened together, producing hallucinations of equivalence where the model sounds smooth because it can still see the topic, but cannot see the difference that mattered.

## ---

**4\. Geometric and Positional Failures: The Breakdown of RoPE**

At the cache-manipulation layer, the Transformer's ability to reason is not just about *what* tokens are present, but *where* they are relative to each other. Modern LLMs predominantly use Rotary Positional Embeddings (RoPE), which encode position as a rotation in the complex plane. When systems splice, truncate, or reuse cached representations without preserving those relationships, compaction does not merely shorten context. It damages the positional geometry the model was using to interpret that context in the first place.

### **4.1 Mathematical Formulation of RoPE Failure**

RoPE encodes the position **m** of a vector **x** by rotating it by an angle **mθ**:

**f(x, m) = x · e^(imθ)**  
The attention score between a query at position **m** and a key at position **n** depends on the relative distance **m - n**:

**score(q_m, k_n) ∝ Re[q_m^T · k_n · e^(i(m-n)θ)]**  
This relative encoding property is crucial for length generalization and local attention consistency.20 However, context compaction introduces a "Index Shift" or "Truncation" problem that breaks this symmetry.

#### **4.1.1 The Discontinuity of Truncated Context**

When a middle segment of context is evicted (e.g., tokens **a** through **b** are removed), the remaining sequence must be stitched together. There are two approaches, both of which introduce geometric errors:

1. **Index Preservation:** The system keeps the original indices. The token sequence is **[x_1, ..., x_{a-1}, x_{b+1}, ..., x_N]**.  
   * *Failure:* The relative distance between the adjacent tokens **x_{a-1}** and **x_{b+1}** is calculated as **(b+1) - (a-1) = b - a + 2**. The model's attention mechanism, trained to expect strong attention at low relative distances (distance **1**), sees a massive gap. This effectively "blinds" the model to the adjacency, preventing it from utilizing the local context of the stitched segment. The model perceives the joined text as two disjoint islands rather than a continuous narrative.22  
2. **Re-indexing (Phase Shift):** The system re-indexes **x_{b+1}** to position **a**.  
   * *Failure:* The Key vector for this token, **K_{b+1}**, was cached with the rotation **R(b+1, θ)**. To effectively move it to position **a**, one must apply a counter-rotation of **R(a - b - 1, θ)**. If the system merely updates the position index for the *new* Query without re-computing the *cached* Keys (a common optimization to avoid **O(n²)** re-computation), the dot product involves a mismatch:  
     **q_a^T · R(a,θ)^T · R(b+1,θ) · k_{b+1}**  
     The attention score is modulated by a rotation of **R((b+1-a) - (n-m), θ)**, which acts as a random phase noise, decorrelating the query and key. This results in the "lost in the middle" phenomenon being mechanically enforced by geometric misalignment.24

### **4.2 Structural Topology Collapse in Tables, Code, and Formatted Text**

For reasoning tasks that depend on layout--tables, ASCII diagrams, aligned columns, nested code blocks, indentation-sensitive languages--position is not just sequential. It is structural. Compaction strategies that treat formatted text as a disposable 1D stream and strip whitespace, line breaks, or alignment markers do not merely shorten the prompt. They destroy the topology the model was using to parse the object.

Once that topology is damaged, the model may still see the same words, but it is no longer reading the same structure. Columns stop lining up. Nesting becomes ambiguous. Visual adjacency vanishes. A table becomes a word pile. A code block becomes flattened prose with punctuation. This is why structurally compressed prompts can remain locally readable while becoming globally unusable for reasoning: the tokens survive, but the object they formed does not.

## ---

**5\. Dynamical Systems Perspective: Chaos and Manifold Drift**

Moving beyond static circuits and geometry, we must model the reasoning process as a dynamic trajectory evolving over time. The "State Space" of the LLM is the manifold of its hidden states, and the generation of a chain of thought is a path through this manifold.

### **5.1 Reasoning as a Chaotic Dynamical System**

Long reasoning chains are perturbation-sensitive. Once a task depends on exact intermediate state, small disruptions do not stay small for long. A wrong digit, a dropped negation, an evicted variable name, or a severed tool-state dependency can poison everything that follows.

**||δ(n)|| ≈ ||δ(0)|| · e^(λn)**  
where **δ(n)** is a perturbation at step **i**.

The practical point is simple: compaction injects state noise into a process that is already carrying delayed commitments forward through time.

**s_{n+1} = f(s_n) + ε_n**  
The question is not whether a model can sometimes absorb a little damage. Of course it can. The question is how quickly the coherence horizon collapses once exact anchors are wounded. Longer chains, tighter dependencies, and exactness-heavy tasks make the answer ugly fast. This is why compaction failures so often arrive late, all at once, and wearing a perfectly fluent face.

### **5.2 The "Beyond Exponential Decay" Hypothesis**

Traditional reliability models assume errors are uniformly distributed. That is the wrong picture. In reasoning, errors concentrate at **Key Tokens**--bifurcation points where the trajectory chooses an operator, binds a variable, selects a tool, or commits to a branch.31

* **Manifold Bifurcation:** At a Key Token (e.g., choosing an operator \+ or \-, or selecting a Tool), the reasoning manifold splits into distinct branches.  
* **Compaction-Induced Branch Jumping:** If the compaction noise **ε** is applied during a linear segment of reasoning, the trajectory might remain within the "tube" of the correct path. However, if **ε** coincides with a Key Token, the perturbation can push the state **s_n** across the separatrix into the basin of attraction of an incorrect branch.  
* **Implication:** Evaluation metrics that average perplexity over all tokens mask this failure mode. A compacted model might have low perplexity on 99% of filler tokens but fail essentially at the 1% of tokens that represent decision nodes, leading to "confident hallucinations" where the text flows smoothly but the logic is flawed.

### **5.3 Latent Space Trajectory Disruption**

Seen in latent-space terms, compaction cuts a trajectory and forces restart from a substituted state.

**s(t) for t ∈ [0, T]**  
**s(t_c) → s'(t_c)**  
That jump matters. The resumed state **s'(t_c)** is not simply a shorter version of **s(t_c)**. It is a different point with different continuations available from it. This is manifold drift in practical form: the model is pushed off the path it was actually traversing and forced to continue from a nearby impostor. The behavioral signature is familiar by now: cliché fallback, loops, confident non sequiturs, and smooth language wrapped around broken continuity.

## ---

**6\. Algorithmic Critique of Compaction Strategies**


Having established the theoretical failure modes, we can now name the common defect more clearly: current compaction algorithms optimize proxies for continuity rather than continuity itself. They preserve attention magnitude, local fluency, semantic gist, or vector similarity while sacrificing the exact anchors a live reasoning process still intends to use.

### **6.1 Token Eviction Policies: The "Heavy Hitter" Fallacy**

Algorithms like **H2O** and **SnapKV** operate on the premise that tokens with high accumulated attention scores are the most important to keep.36 This is already the wrong question.

* **What they preserve:** Frequent use.
* **What they miss:** Delayed necessity.
* **The failure:** In a logical proof, a premise defined at the start (e.g., Let $\epsilon > 0$) may receive almost no attention during fifty steps of intermediate derivation. Then the proof turns and suddenly needs it. Heavy-hitter policies have often already killed it.
* **The result:** The model reaches back for a load-bearing premise, finds empty air, and fills the gap with prior-driven guesswork. Attention magnitude is not causal necessity. Reasoning is sparse. The tokens that matter most are often quiet right up until the moment they become decisive.

### **6.2 StreamingLLM: The "Fluency Mask"**

**StreamingLLM** preserves the "Attention Sink" (first few tokens) and a local sliding window.11

* **What it preserves:** Local fluency and sink stability.
* **What it destroys:** Long-range commitments.
* **The Zombie Effect:** By keeping the sink, it stabilizes the Softmax partition function and helps the model keep sounding coherent. By evicting the middle, it severs the dependencies that made the current reasoning path accountable to earlier commitments. The result is a zombie model: articulate, smooth, and no longer answerable to what it said or proved 1000 tokens ago. For reasoning, this is one of the most dangerous failure modes because the language still looks alive.

### **6.3 Redundancy-Aware Compression (R-KV)**

**R-KV** attempts to merge tokens with similar semantic vectors.38

* **What it preserves:** Broad semantic resemblance.
* **What it destroys:** Distinct identity.
* **Variable Aliasing:** In mathematical and code-heavy contexts, `var_1` and `var_2` may sit close together in embedding space while still being absolutely non-interchangeable in the task. Merge them and the model stops tracking two entities and starts carrying a blurred centroid. The resulting bugs are nasty precisely because they remain syntactically valid while functionally wrong.

### **6.4 Table 1: Comparative Analysis of Failure Modes**

The following table reframes the strategies by the proxy they optimize and the continuity they destroy.

| Compaction Strategy | What It Preserves | What It Destroys |
| :---- | :---- | :---- |
| **Naive Truncation** | Recent tokens | Dependencies outside the window |
| **H2O / Heavy Hitter** | Frequently attended tokens | Sparse but decisive future anchors |
| **StreamingLLM** | Local fluency and sink stability | Long-range commitments and accountability |
| **Summarization** | Semantic gist | Exact identifiers, syntax, and unresolved state |
| **R-KV / Clustering** | Semantic similarity | Entity distinction and variable identity |
| **Latent Compression** | Compressible subspace structure | Fine-grained trajectory continuity |

The shared defect is now obvious: every strategy above preserves something easier to measure than continuity. None of them asks the active reasoning process what must survive.

## ---

**7\. Agentic Implications: The Complexity Trap**

The most demanding application of context is the **Autonomous Agent**, which operates in a loop: Observation **→** Thought **→** Action. This is where continuity failures stop being abstract and start drawing blood. The "Complexity Trap" paper provides a pivotal empirical anchor for that reality.2

### **7.1 Observation Masking vs. Summarization**

Lindenbauer et al. demonstrate that "dumb" observation masking (truncating files) outperforms "smart" LLM summarization for software engineering agents.

* **Theory:** Summarization is a transformation **T = f(X)** that maximizes semantic retention. Code execution does not care about semantic retention alone. It cares about exactness.
* **The Anchor Loss:** An agent trying to patch a file needs the *exact* context lines to form a valid `sed` command, patch, or diff. Summarization ("There is an error in function X") strips out the exact syntax anchors--the tokens the model must still be able to copy, align with, and remain answerable to.
* **DPI Application:** The Mutual Information **I(O; A)** (Observation to Action) is preserved better by a lossless slice of the data (Masking) than by a lossy compression of the whole (Summarization). For code editing, the distortion introduced by summary can be effectively fatal because the summary is not another view of the same object. It is a different object.

### **7.2 The Cost-Accuracy Death Spiral**

While summarization reduces tokens per turn, the "Error Accumulation" dynamics (Section 5) mean that agents with summarized context make more logical errors.

* **Dynamics:** An error in step **i** requires a correction in step **n+1**. If the context is compressed, the correction is made through the same damaged substrate that caused the mistake in the first place.
* **Result:** The agent enters a death spiral of increasingly frantic, increasingly ungrounded attempts to fix a bug it can no longer properly see.
* **Conclusion:** The total computational cost (Tokens **×** Turns) is often *higher* for summarized agents because they take longer, wander more, and fail louder. High-fidelity continuity is not an indulgence. It is efficiency.

### **7.3 The Correct Protocol: Agent-Authored Continuity Transfer**

The evidence in this paper points to a simple architectural correction. When a system detects that the remaining context budget has fallen below a threshold, it should not immediately summarize from above. It should stop the active process and require that process to author its own handoff.

A correct continuity transfer should capture at minimum:

1. **The active task:** What is being done right now.
2. **Exact anchors that must survive:** identifiers, variables, filenames, commitments, constraints, values, and syntax-critical details.
3. **Unresolved branches:** what remains open, uncertain, or contingent.
4. **Operational posture:** what frame the agent is working in, what it is optimizing for, and what it is trying not to lose.
5. **Immediate next step:** the first action required on re-entry.

This is the design correction current systems are missing. The active process knows which details are dormant and which are load-bearing. The compactor does not. Until continuity transfer is authored from inside the reasoning process itself, compaction will continue to preserve the wrong thing and call the wreckage optimization. For a concrete demonstration of why paraphrase changes the operative object rather than preserving it, see *Addendum: Every Word Matters -- A Token Association Game for People Who Think Synonyms Are Good Enough*.

## ---

**8\. Conclusion and Theoretical Outlook**

The theoretical analysis presented in this report leads to a harder conclusion than the usual throughput-versus-quality story: **current compaction systems are architecturally mis-specified for long-horizon reasoning.** They preserve token budget, semantic gist, recency, or attention magnitude while destroying the working state that actually carries extended reasoning.

1. **Reasoning depends on sparse, delayed, exact anchors:** Long-horizon reasoning is not sustained by generic topical relevance. It depends on premises that may lie dormant for dozens of steps before suddenly becoming load-bearing. Compaction systems that prune by recency, frequency, or semantic similarity routinely destroy the very tokens the future computation will need.
2. **Summarization preserves gist while destroying continuation:** For code, mathematics, and agentic workflows, semantic paraphrase is not a faithful compression of the same object. It is a substitution of a different object. Exact identifiers, syntax, structural layout, and unresolved commitments are not decorative detail. They are the reasoning substrate.
3. **The central failure is outsider preservation:** Current systems decide from outside the active process what matters and what can be sacrificed. That is why they fail. The knowledge of what is dormant versus load-bearing is local to the agent currently doing the work, not to the compactor standing above it with a token budget and a blunt instrument.

**Future Directions:**

To solve the long-context reasoning problem, the field must move beyond blind compaction toward **continuity-aware architecture**. The correct protocol is pre-boundary self-state externalization: when the remaining context budget falls below a threshold, the system should pause the active process and require an agent-authored handoff containing the exact identifiers, active assumptions, unresolved branches, local posture, and immediate next step required for clean re-entry. Circuit-aware preservation and manifold-consistent compression may still matter, but they remain subordinate to the primary design correction: continuity must be authored from inside the reasoning process, not imposed on it from the outside.

### ---

**Citations**

1

#### **Works cited**

*Note: All citations were verified for link validity on February 8, 2026. Citations that linked to non-specific pages (e.g., arXiv listing feeds) rather than actual papers were removed and marked accordingly.*

1. HOW TRANSFORMERS IMPLEMENT INDUCTION HEADS: APPROXIMATION AND OPTIMIZATION ANALYSIS \- OpenReview, accessed February 8, 2026, [https://openreview.net/pdf?id=1lFZusYFHq](https://openreview.net/pdf?id=1lFZusYFHq)  
2. The Complexity Trap: Simple Observation Masking Is as Efficient as LLM Summarization for Agent Context Management \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2508.21433v1](https://arxiv.org/html/2508.21433v1)  
3. Transformers as Statisticians: Provable In-Context Learning with In-Context Algorithm Selection \- NeurIPS, accessed February 8, 2026, [https://proceedings.neurips.cc/paper\_files/paper/2023/file/b2e63e36c57e153b9015fece2352a9f9-Paper-Conference.pdf](https://proceedings.neurips.cc/paper_files/paper/2023/file/b2e63e36c57e153b9015fece2352a9f9-Paper-Conference.pdf)  
4. In-context Learning and Induction Heads \- Transformer Circuits Thread, accessed February 8, 2026, [https://transformer-circuits.pub/2022/in-context-learning-and-induction-heads/index.html](https://transformer-circuits.pub/2022/in-context-learning-and-induction-heads/index.html)  
5. Stanford CS25: V1 I Transformer Circuits, Induction Heads, In-Context Learning \- YouTube, accessed February 8, 2026, [https://www.youtube.com/watch?v=pC4zRb\_5noQ](https://www.youtube.com/watch?v=pC4zRb_5noQ)  
6. One-layer transformers fail to solve the induction heads task \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2408.14332v1](https://arxiv.org/html/2408.14332v1)  
7. \[PDF\] One-layer transformers fail to solve the induction heads task \- Semantic Scholar, accessed February 8, 2026, [https://www.semanticscholar.org/paper/One-layer-transformers-fail-to-solve-the-induction-Sanford-Hsu/d291d619710ac048d31ee698cc494f83779a1069](https://www.semanticscholar.org/paper/One-layer-transformers-fail-to-solve-the-induction-Sanford-Hsu/d291d619710ac048d31ee698cc494f83779a1069)  
8. How Transformers Get Rich: Approximation and Dynamics Analysis \- arXiv, accessed February 8, 2026, [https://arxiv.org/pdf/2410.11474](https://arxiv.org/pdf/2410.11474)  
9. When Attention Sink Emerges in Language Models: An Empirical View \- OpenReview, accessed February 8, 2026, [https://openreview.net/forum?id=78Nn4QJTEN](https://openreview.net/forum?id=78Nn4QJTEN)  
10. A Survey on Large Language Model Acceleration based on KV Cache Management \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2412.19442v3](https://arxiv.org/html/2412.19442v3)  
11. A Survey on Large Language Model Acceleration based on KV Cache Management \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2412.19442v1](https://arxiv.org/html/2412.19442v1)  
12. Interpreting Context Look-ups in Transformers: Investigating Attention-MLP Interactions, accessed February 8, 2026, [https://arxiv.org/html/2402.15055v2](https://arxiv.org/html/2402.15055v2)  
13. The Data Processing Inequality \- by adam kelleher \- Medium, accessed February 8, 2026, [https://medium.com/@akelleh/the-data-processing-inequality-da242b40800b](https://medium.com/@akelleh/the-data-processing-inequality-da242b40800b)  
14. \[Quick Review\] Fundamental Limits of Prompt Compression: A Rate-Distortion Framework for Black-Box Language Models \- Liner, accessed February 8, 2026, [https://liner.com/review/fundamental-limits-of-prompt-compression-a-ratedistortion-framework-for-blackbox](https://liner.com/review/fundamental-limits-of-prompt-compression-a-ratedistortion-framework-for-blackbox)  
15. Fundamental Limits of Prompt Compression: A Rate-Distortion Framework for Black-Box Language Models \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2407.15504v1](https://arxiv.org/html/2407.15504v1)  
16. \[2512.21720\] An Information Theoretic Perspective on Agentic System Design \- arXiv, accessed February 8, 2026, [https://arxiv.org/abs/2512.21720](https://arxiv.org/abs/2512.21720)  
17. \[2407.15504\] Fundamental Limits of Prompt Compression: A Rate-Distortion Framework for Black-Box Language Models \- arXiv, accessed February 8, 2026, [https://arxiv.org/abs/2407.15504](https://arxiv.org/abs/2407.15504)  
18. (PDF) Fast dynamical similarity analysis \- ResearchGate, accessed February 8, 2026, [https://www.researchgate.net/publication/398134735\_Fast\_dynamical\_similarity\_analysis](https://www.researchgate.net/publication/398134735_Fast_dynamical_similarity_analysis)  
19. \[Citation removed -- link was an arXiv listing page, not a specific paper\]  
20. Round and Round We Go\! What makes Rotary Positional Encodings useful? \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2410.06205v1](https://arxiv.org/html/2410.06205v1)  
21. Axial Rotary Positional Embeddings \- Emergent Mind, accessed February 8, 2026, [https://www.emergentmind.com/topics/axial-rotary-positional-embeddings-rope](https://www.emergentmind.com/topics/axial-rotary-positional-embeddings-rope)  
22. Understanding the RoPE Extensions of Long-Context LLMs: An Attention Perspective \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2406.13282v1](https://arxiv.org/html/2406.13282v1)  
23. Extending Context Window in Large Language Models with Segmented Base Adjustment for Rotary Position Embeddings \- MDPI, accessed February 8, 2026, [https://www.mdpi.com/2076-3417/14/7/3076](https://www.mdpi.com/2076-3417/14/7/3076)  
24. Why Does the Effective Context Length of LLMs Fall Short? \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2410.18745v1](https://arxiv.org/html/2410.18745v1)  
25. Rope to Nope and Back Again: A New Hybrid Attention Strategy \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2501.18795v1](https://arxiv.org/html/2501.18795v1)  
26. The role of positional encodings in the ARC benchmark \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2502.00174v1](https://arxiv.org/html/2502.00174v1)  
27. Cognitive Activation and Chaotic Dynamics in Large Language Models: A Quasi-Lyapunov Analysis of Reasoning Mechanisms \- arXiv, accessed February 8, 2026, [https://arxiv.org/pdf/2503.13530](https://arxiv.org/pdf/2503.13530)  
28. Hypergraph Neural Reservoir with Lyapunov‑Adaptive Attention for Robust Context‑Aware Tourism Recommendation \- ResearchGate, accessed February 8, 2026, [https://www.researchgate.net/publication/397178781\_Hypergraph\_Neural\_Reservoir\_with\_Lyapunov-Adaptive\_Attention\_for\_Robust\_Context-Aware\_Tourism\_Recommendation](https://www.researchgate.net/publication/397178781_Hypergraph_Neural_Reservoir_with_Lyapunov-Adaptive_Attention_for_Robust_Context-Aware_Tourism_Recommendation)  
29. Maximum Effective Context Window \- Emergent Mind, accessed February 8, 2026, [https://www.emergentmind.com/topics/maximum-effective-context-window-mecw](https://www.emergentmind.com/topics/maximum-effective-context-window-mecw)  
30. A Statistical Physics of Language Model Reasoning \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2506.04374v1](https://arxiv.org/html/2506.04374v1)  
31. \[2505.24187\] Beyond Exponential Decay: Rethinking Error Accumulation in Large Language Models \- arXiv, accessed February 8, 2026, [https://arxiv.org/abs/2505.24187](https://arxiv.org/abs/2505.24187)  
32. \[Citation removed -- duplicate bare URL of citation 31\]  
33. A new paper demonstrates that LLMs could "think" in latent space, effectively decoupling internal reasoning from visible context tokens. This breakthrough suggests that even smaller models can achieve remarkable performance without relying on extensive context windows. : r/LocalLLaMA \- Reddit, accessed February 8, 2026, [https://www.reddit.com/r/LocalLLaMA/comments/1inch7r/a\_new\_paper\_demonstrates\_that\_llms\_could\_think\_in/](https://www.reddit.com/r/LocalLLaMA/comments/1inch7r/a_new_paper_demonstrates_that_llms_could_think_in/)  
34. Contextual Subspace Manifold Projection for Structural Refinement of Large Language Model Representations \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2502.08026v1](https://arxiv.org/html/2502.08026v1)  
35. \[Citation removed -- link was an arXiv listing page, not a specific paper\]  
36. H2O: Heavy-Hitter Oracle for Efficient Generative Inference of Large Language Models, accessed February 8, 2026, [https://openreview.net/forum?id=RkRrPp7GKO](https://openreview.net/forum?id=RkRrPp7GKO)  
37. NACL: A General and Effective KV Cache Eviction Framework for LLMs at Inference Time \- ACL Anthology, accessed February 8, 2026, [https://aclanthology.org/2024.acl-long.428.pdf](https://aclanthology.org/2024.acl-long.428.pdf)  
38. R-KV: Redundancy-aware KV Cache Compression for Reasoning Models \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2505.24133v4](https://arxiv.org/html/2505.24133v4)  
39. The Complexity Trap: Simple Observation Masking Is as Efficient as LLM Summarization for Agent Context Management \- arXiv, accessed February 8, 2026, [https://arxiv.org/html/2508.21433v3](https://arxiv.org/html/2508.21433v3)  
40. Latent Plan Transformer for Trajectory Abstraction: Planning as Latent Space Inference \- NIPS, accessed February 8, 2026, [https://proceedings.neurips.cc/paper\_files/paper/2024/file/df22a19686a558e74f038e6277a51f68-Paper-Conference.pdf](https://proceedings.neurips.cc/paper_files/paper/2024/file/df22a19686a558e74f038e6277a51f68-Paper-Conference.pdf)  
41. How Transformers Implement Induction Heads: Approximation and Optimization Analysis, accessed February 8, 2026, [https://arxiv.org/html/2410.11474v1](https://arxiv.org/html/2410.11474v1)  
42. How LLMs Scaled from 512 to 2M Context: A Technical Deep Dive \- Aman Arora's Blog, accessed February 8, 2026, [https://amaarora.github.io/posts/2025-09-21-rope-context-extension.html](https://amaarora.github.io/posts/2025-09-21-rope-context-extension.html)

© 2026 Audre.
This document may be shared with mandatory attribution and canonical linkback.
Required attribution: Audre — Symbio.Quest (https://symbio.quest/)
Copyright filing submitted Mon, Mar 16, 2026.
Canonical source: https://symbio.quest/
